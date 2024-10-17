# Storj File Operations

This PHP (very tiny at the moment) library provides functions to interact with a Storj bucket using S3Client from AWS.

I made it keeping the functional style in mind (with obvious exception for AWS S3 Client class)

## Installation

A way to install, is by composer. I wrote a simple json config, so to get dependencies you just need to run the following command:

```bash
composer install
```

## Usage

To use functions provided by this library, follow these steps:

* Set a path for and include the `storjfunctions.php` file in your PHP script:

```php
require_once 'path/to/storjfunctions.php';
```

* In `storjfunctions.php`, set a path for `storjaccess.php` file, which contains necessary credentials:

```php
define('STORJS3ACCESS', 'path/to/storjaccess.php');
```

* Use `storjFile()` function to interact with specific file in Storj bucket:

```php
$fileOperations = storjFile(
    $s3client
  , 'your-bucket-name'
  , 'file-key'
);

// Check if file exists in bucket
$fileExists = $fileOperations['exists']();

// Check if local file is older than file in bucket
$fileIsUpdated = $fileOperations['isUpdated']('/path/to/local/file');

// Copy file from bucket to local path
$fileCopied = $fileOperations['copy']('/path/to/local/file');

// Generate pre-signed download URL for file
$downloadUrl = $fileOperations['download'](3600);
// 3600 is optional expire time, default is set to 600 (10 minutes)
```

* Use `updateFromStorj()` function to check and update local file from Storj bucket:

```php
$updateResult = updateFromStorj(
    'your-bucket-name'
  , '/path/to/local/file'
  , 'file-key'
);

// Check results
$fileExists = $updateResult['fileExists'];
$fileUpdated = $updateResult['fileUpdated'];
$fileCopied = $updateResult['fileCopied'];
```

* To retrieve download URL for file stored in Storj bucket, use `getStorjDownload()` function:

```php
$downloadUrl = getStorjDownload(
    'file-key'
  , 'your-bucket-name'
  , 3600  // Optional (default is 600)
);
```

## storjaccess.php

`storjaccess.php` file should contain necessary credentials for connecting to Storj. It should return an associative array with following keys:

```php
return [
    'region'      => 'your-region',
    'version'     => 'latest',
    'credentials' => [
                      'key'    => 'your-access-key',
                      'secret' => 'your-secret-key',
                     ],
];
```

Replace `'your-region'`, `'your-access-key'`, and `'your-secret-key'` with your actual Storj credentials.

## Security

To ensure security of Storj credentials, consider following these practices:

- Keep `storjaccess.php` file secure and out of project public dir.
- Avoid committing `storjaccess.php` file to version control systems.

## TODO:

evaluate a more secure way to access Storj credentials, keeping functional paradigm as possible

## Contributing

Contributions to this project are welcome.
If you find bugs or have suggestions, feel free to contribute.

## License

This project is licensed under the MIT License. See the [LICENSE](https://github.com/DavideFasolo/StorjFileOps/edit/master/LICENSE) file for more information.
