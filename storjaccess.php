<?php
/**
 * This configuration file returns an array for initializing S3Client in AWS
 * SDK for PHP, tailored to work with Storj's S3-compatible gateway
 * 
 * Ensure to replace values with your actual credentials and region settings
 */

return [
   // AWS region where your Storj is located.
   // Storj provides three main regions for its gateway: EU1, US1, AP1.
   // Replace 'region' with the desired region
   'region' => 'region',

   // API version to use
   // 'latest' ensures most recent version
   'version' => 'latest',

   // Endpoint URL for Storj's S3-compatible gateway
   // URL is usually fixed and should not be changed
   'endpoint' => 'https://gateway.storjshare.io',

   // Access to bucket by path-style URLs
   // Set it true to ensure compatibility with Storj S3 gateway
   'use_path_style_endpoint' => true,

   // Credentials used to authenticate Storj gateway
   'credentials' => [
      // Access key for your Storj
      // Replace 'your_storj_API_key' with your Storj API key
      'key' => 'your_storj_API_key',

      // Secret key corresponding Storj API key
      // Replace 'your_storj_secret' with your torj API secret
      'secret' => 'your_storj_secret',
   ]
];
