<?php
/**
 * storjfunctions.php
 * 
 * @author Davide Fasolo <davide@fasolodesign.it>
 *
 * This file contains functions to interact with a Storj bucket using AWS S3 Client SDK.
 * It provides utilities to check the existence of files, compare their modification times
 * with local files, copy files from the bucket to a local path, and generate pre-signed
 * download URLs with expiration for public access
 *
 * 
 * Functions:
 *
 * - connectS3storj(): Creates and returns an S3Client instance configured for Storj
 *   interaction
 *
 * - storjFile: Returns an array of closures for performing various file operations such as
 *   checking existence, copying, comparing modification times, and generating a downloadable
 *   URL for a specific file in a Storj bucket
 *
 * - updateFromStorj: Checks if a local file is up-to-date compared to a file in a Storj bucket
 *   and updates it if needed, returning an array of results
 *
 * - getStorjDownload: Generates and returns a pre-signed URL for downloading a file from a Storj
 *   bucket with an optional expiration time
 * 
 * 
 * Dependancies:
 * @requires aws-sdk-php
 *           use composer to install
 * 
 */

// namespaces for readability
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

// 
/**
 * use a secure path to store 'storjaccess.php'
 * // TODO: better secutiry for importing these credentials
 * (maybe encryption or something like)
 */
define(
      'STORJS3ACCESS'
    , './storjaccess.php'
);


/**
 * Memoize functionality for caching results of expensive function calls
 *
 * This takes a callable as input and returns new function that caches results
 * of the original function. If same arguments are passed to the memoized
 * function, will return cached result instead repeating execution
 *
 * @param callable $fn Original function to memoize
 *
 * @return callable Memoized function
 */
function memoize(callable $fn): callable
{
    $cache = [];
    return function (...$args) use ($fn, &$cache) {
        $key = serialize($args);
        if (!isset($cache[$key])) {
            $cache[$key] = $fn(...$args);
        }
        return $cache[$key];
    };
}

/**
 * Creates and returns an S3Client instance configured for Storj interaction
 *
 * This function is responsible for establishing connection to Storj network
 * using AWS S3 Client SDK
 * Includes the necessary configuration parameters
 * @see constant STORJS3ACCESS
 * @see storjaccess.php
 *
 * @return S3Client Instance of the S3Client class
 *                  configured for Storj interaction
 *                  @see aws-sdk-php
 *
 * @throws InvalidArgumentException If 'storjaccess.php' file does not exist
 *                                  or contains invalid parameters
 * 
 */
function connectS3storj(): S3Client   
{
    return new S3Client(
        include(STORJS3ACCESS)
    );
}

/**
 * This function interacts with a Storj bucket to perform operations
 * on a specific file
 *
 **@param S3Client $s3client Instance of the S3Client class provided by
 *                           AWS SDK for PHP
 * @param string   $bucket Name of Storj bucket where the file is located
 * @param string   $fileBucketPath Name of the file in bucket
 *                                 (usually defined as "file key")
 *
 * @return array  Associative array containing closures for various file operations:
 * - 'exists':    Returns true if file exists in bucket, false otherwise
 * - 'isUpdated': Returns true if local file is older than file in bucket, false otherwise
 * - 'copy':      Copies file from bucket to local path and returns true if successful, false otherwise
 */
function storjFile($s3client, $bucket, $fileBucketPath): array
{
    $remoteFile = function () use ($bucket, $fileBucketPath): array {
        return [
            'Bucket' => $bucket,
            'Key'    => $fileBucketPath,
        ];
    };

    $BucketFileHead = memoize(
        function () use ($s3client, $remoteFile): ?array {
            try {
                return $s3client->headObject($remoteFile());
            } catch (S3Exception $e) {
                return null;
            }
        }
    );

    $bucketFileMtime = function () use ($BucketFileHead): bool|int {
        return $BucketFileHead() !== null ?
            strtotime($BucketFileHead()['LastModified'])
          : false;
    };

    $BucketFileContent = memoize(
        function () use ($s3client, $remoteFile): ?array {
            try {
                return $s3client->getObject($remoteFile());
            } catch (S3Exception $e) {
                return null;
            }
        }
    );

    $expireIn = function (int $seconds): string {
        return '+' . $seconds . ' seconds';
    };

    $generateDownloadUrl = function (int $expiretime = 600) use ($s3client, $remoteFile, $expireIn): ?string {
        try {
            return $s3client->getObjectUrl(
                $remoteFile()['Bucket'],
                $remoteFile()['Key'],
                $expiretime
            );
        } catch (S3Exception $e) {
            return null;
        }
    };

    return [
        'exists' => function () use ($BucketFileHead): bool {
            try {
                return $BucketFileHead() !== null;
            } catch (S3Exception $e) {
                return false;
            }
        },

        'isUpdated' => function ($local_path) use ($bucketFileMtime): bool {
            try {
                return $bucketFileMtime() && file_exists($local_path) ?
                    filemtime($local_path) >= $bucketFileMtime()
                  : false;
            } catch (S3Exception $e) {
                return false;
            }
        },

        'copy' => function ($local_path) use ($BucketFileContent): bool {
            try {
                return isset($BucketFileContent()['Body']) ?
                    file_put_contents($local_path, $BucketFileContent()['Body']) !== false
                  : false;
            } catch (S3Exception $e) {
                return false;
            }
        },

        'download' => function (int $seconds = 600) use ($generateDownloadUrl): ?string {
            return $generateDownloadUrl($seconds);
        }
    ];
}

/**
 * Check and update a local file from a Storj bucket
 *
 * @return array  Associative array containing boolean feedbacks for:
 * - 'fileExists': True if file exists in Storj bucket
 * - 'fileUpdated': True if local file is equal | newer than Storj bucket one
 * - 'fileCopied': True if file was successfully copied from Storj bucket to
 *                 local path
 */
function updateFromStorj($bucketname, $filelocalpath, $filebucketpath): array {
    $storjOps = memoize(function () use ($bucketname, $filebucketpath): array {
        return storjFile(connectS3storj(), $bucketname, $filebucketpath);
    })();

    return [
        'fileExists'  => $storjOps['exists'](),

        'fileUpdated' => $storjOps['exists']() &&
                         $storjOps['isUpdated']($filelocalpath),

        'fileCopied'  => (
                            !(
                                 $storjOps['exists']() &&
                                 $storjOps['isUpdated']($filelocalpath)
                             ) && $storjOps['exists']()
                         ) && $storjOps['copy']($filelocalpath)
    ];
}

/**
 * Retrieves download URL for a file stored in a Storj bucket
 *
 * This function interacts with a Storj bucket to generate download URL string
 * for a specified file
 * If file does not exist in the bucket, function returns null
 *
 * @param string $fileremotepath Bucket file remote path (file key)
 * @param string $bucketname Bucket name
 * @param int $expiretime Download expiration time, in seconds
 *                        Default 600 (10 minutes)
 *
 * @return ?string File download URL string, if exists in bucket, null otherwise
 */
function getStorjDownload($fileremotepath, $bucketname, $expiretime = 600): ?string {
    $storjOps = memoize(function () use ($bucketname, $fileremotepath): array {
        return storjFile(connectS3storj(), $bucketname, $fileremotepath);
    })();
    return $storjOps['exists']() ?
            $storjOps['download']($expiretime)
          : null;
}
