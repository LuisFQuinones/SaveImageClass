<?php

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

require "../../config/mainModel.php";
require "../../config/composer/vendor/autoload.php";

$url = $_GET['url'];
$url = str_replace($GLOBALS["AWS_BUCKET_URL"], '', $url);

$BD = new BD();
$s3Options = $BD->s3Options();

$s3 = new S3Client($s3Options);

try {
    $result = $s3->getObject([
        'Bucket' => $GLOBALS["AWS_BUCKET"],
        'Key' => $url
    ]);

    header('Content-Type: ' . $result['ContentType']);
    header('Content-Length: ' . $result['ContentLength']);
    echo $result['Body'];
} catch (AwsException $e) {
    echo $e->getMessage();
}
