# SaveImage Class

## Description

The `saveImage` class is a robust PHP solution for uploading and managing images with AWS S3 integration. It provides functionalities for validating, resizing, and saving images, including handling different image types such as JPEG, PNG, and SVG. This class ensures efficient image handling, including generating thumbnails and managing image quality to meet specified size constraints.

## Features

- **AWS S3 Integration**: Seamlessly upload images to AWS S3 buckets.
- **Image Validation**: Validate image type, size, and folder existence.
- **Image Resizing**: Resize images maintaining aspect ratio.
- **Thumbnail Generation**: Create and save image thumbnails.
- **Quality Adjustment**: Dynamically adjust image quality to meet size constraints.
- **Base64 Handling**: Process images provided in base64 format.
- **CORS Handling**: Fetch images directly from AWS S3 to avoid CORS errors.

## Installation

To use the `saveImage` class, ensure you have the required dependencies installed via Composer. Include the following in your `composer.json`:

```json
{
    "require": {
        "aws/aws-sdk-php": "^3.0",
        "php": ">=7.0"
    }
}
```
## Usage

Below is an example of how to use the saveImage class to upload an image to AWS S3 and generate a thumbnail:

```php
require "../config/composer/vendor/autoload.php";
$saveImage = new saveImage();
$imagen_prueba = $_FILES['imagen'];
$response = $saveImage->guardar_imagen_prueba($imagen_prueba);

if ($response['status'] == 1) {
    echo "Image uploaded successfully!";
    echo "Thumbnail path: " . $response['thumbnail'];
    echo "Image path: " . $response['ruta'];
} else {
    echo "Error: " . $response['mensaje'];
}
```

## Method: guardar_imagen_prueba

```php
public function guardar_imagen_prueba($icono)
```

### Parameters:

- icono: The image file received from a form submission ($_FILES).
### Returns:

- An array containing the status, thumbnail path, and image path or an error message.

### Method: saveImageS3
```php
public function saveImageS3($image, $name, $type, $folder, $thumbnail, $maxSize = 5)
```

### Parameters:

- image: The image content (binary or base64).
- name: The desired name for the image.
- type: The image type (jpg, jpeg, png, svg).
- folder: The S3 folder where the image will be saved.
- thumbnail: Boolean indicating whether to create a thumbnail.
- maxSize: The maximum allowed size for the image in MB (default is 5MB).

### Returns:

- An array containing the status, thumbnail path, and image path or an error message.

## Fetch Image from S3 to Avoid CORS

To avoid CORS errors when fetching images from AWS S3, use the following example:
```php
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
```

## License
This project is licensed under the MIT License. See the LICENSE file for details.

## Contributing
Contributions are welcome! Please fork this repository and submit a pull request for any improvements or bug fixes.

## Author
Luis Fernando Quiñones.
