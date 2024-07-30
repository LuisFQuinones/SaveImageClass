<?php

use Aws\S3\S3Client;

require "../../config/composer/vendor/autoload.php";

class saveImage
{
    private $BD;

    public function __construct()
    {
        $this->BD = new BD();
        $this->BD->conectar();
    }

    public function saveImageS3($image, $name, $type, $folder, $thumbnail = false, $maxSize = 5, $dimension = [100, 100])
    {
        try {
            if ($type == 'svg+xml') {
                $type = 'svg';
            }
            $image = $this->validations($image, $type, $folder, $maxSize);
            // Funcion para reducir el tamaño de la imagen sin perder calidad
            $image = $this->resizeImageQuality($image, $type, 80);
            $name = str_replace(' ', '_', $name);
            $name .= '_' . time(); // para evitar que se sobreescriban las imágenes con el mismo nombre
            $thumbnailUrl = '';

            if ($thumbnail) {
                $thumbnailUrl = $this->saveThumbnail($image, $name, $type, $folder, $dimension);
                $thumbnailUrl = $GLOBALS["AWS_BUCKET_URL"] . $thumbnailUrl;
            }

            $this->uploadToS3($image, $name, $type, $folder);
            return [
                'status' => '1',
                'thumbnail' => $thumbnailUrl,
                'name' => $GLOBALS["AWS_BUCKET_URL"] . $folder  . $name . '.' . $type,
                'name_image' => $name . '.' . $type
            ];
        } catch (Exception $e) {
            return [
                'status' => '0',
                'error' => $e->getMessage()
            ];
        }
    }

    public function getRouteImage($url)
    {
        try {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domainName = $_SERVER['HTTP_HOST'];

            // Obtener la URI actual
            $currentUri = $_SERVER['REQUEST_URI'];

            // Extraer el camino relativo
            $path = dirname($currentUri);
            if (strpos($path, 'operador') !== false) {
                $path = substr($path, 0, strpos($path, 'operador') + 8);
            } else {
                $path = substr($path, 0, strpos($path, 'tercero') + 7);
            }

            // Construir la URL base completa
            $baseUrl = $protocol . $domainName . $path . '/config/librerias/getImageS3.php?url=' . $url;

            return $baseUrl;
        } catch (Exception $e) {
            return [
                'status' => '0',
                'error' => $e->getMessage()
            ];
        }
    }

    public function deleteImageS3($url)
    {
        $url = str_replace($GLOBALS["AWS_BUCKET_URL"], '', $url);
        $s3 = new S3Client($this->BD->s3Options());
        $result = $s3->deleteObject([
            'Bucket' => $GLOBALS["AWS_BUCKET"],
            'Key' => $url
        ]);

        if (!$result) {
            return [
                'status' => '0',
                'error' => 'No se pudo eliminar la imagen de S3.'
            ];
        }

        return [
            'status' => '1'
        ];
    }

    private function validations($image, $type, $folder, $maxSize)
    {
        if (!$this->validateType($type)) {
            throw new Exception("Tipo de imagen no permitido");
        }
        if (!$this->validateFolder($folder)) {
            throw new Exception("Carpeta no encontrada");
        }

        // si viene en base64
        if (strpos($image, 'data:image') !== false) {
            $image = str_replace('data:image/' . $type . ';base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $image = base64_decode($image);
        }
        // si viene en binario
        else {
            $image = file_get_contents($image);
        }

        if (!$image) {
            throw new Exception("La imagen no se pudo procesar.");
        }

        // Validar tamaño de imagen y ajustar calidad si es necesario
        if ($type !== 'svg') {
            $image = $this->validateSize($image, $type, $maxSize);
        } else {
            $image = $this->validateSvgSize($image, $maxSize);
        }

        return $image;
    }

    private function validateType($type)
    {
        $types = ['jpg', 'jpeg', 'png', 'svg'];
        return in_array($type, $types);
    }

    private function validateSize($image, $type, $maxSize)
    {
        $size = strlen($image);
        if ($size <= $maxSize * 1024 * 1024) {
            return $image;
        }

        $quality = 90;
        while ($size > $maxSize * 1024 * 1024 && $quality > 10) {
            $reducedImage = $this->resizeImageQuality($image, $type, $quality);
            $size = strlen($reducedImage);
            $quality -= 10;
        }

        if ($size > $maxSize * 1024 * 1024) {
            throw new Exception("La imagen no se pudo reducir a un tamaño aceptable.");
        }

        return $reducedImage;
    }

    private function validateSvgSize($image, $maxSize)
    {
        $size = strlen($image);
        if ($size <= $maxSize * 1024 * 1024) {
            return $image;
        }

        $svg = new SimpleXMLElement($image);
        $width = (int)$svg['width'];
        $height = (int)$svg['height'];

        while ($size > $maxSize * 1024 * 1024 && $width > 10 && $height > 10) {
            $width *= 0.9;
            $height *= 0.9;
            $svg['width'] = $width;
            $svg['height'] = $height;
            $image = $svg->asXML();
            $size = strlen($image);
        }

        if ($size > $maxSize * 1024 * 1024) {
            throw new Exception("El SVG no se pudo reducir a un tamaño aceptable.");
        }

        return $image;
    }

    private function validateFolder($folder)
    {
        $s3 = new S3Client($this->BD->s3Options());
        $bucket = $GLOBALS["AWS_BUCKET"];

        $s3->registerStreamWrapper();
        if (!file_exists('s3://' . $bucket . '/' . $folder)) {
            return false;
        } else {
            return true;
        }
    }

    private function saveThumbnail($image, $name, $type, $folder, $dimension)
    {
        if ($type == 'svg') {
            $image = $this->resizeSvg($image, $dimension[0], $dimension[1]);
        } else {
            $image = $this->resizeImage($image, $type, $dimension[0], $dimension[1]);
        }
        // Validar tamaño de la imagen redimensionada
        if (!$this->validateSize($image, $type, 5)) {
            throw new Exception("Tamaño de la miniatura excedido");
        }

        $folder = $folder . 'thumbnails/';
        $thumbnailName = $name . '_thumb';

        $this->uploadToS3($image, $thumbnailName, $type, $folder);

        return $folder . $thumbnailName . '.' . $type;
    }

    private function resizeImage($image, $type, $maxWidth, $maxHeight)
    {
        $icono = imagecreatefromstring($image);
        $ancho = imagesx($icono);
        $alto = imagesy($icono);

        if ($ancho > $alto) {
            $nuevoAlto = ($maxWidth / $ancho) * $alto;
            $nuevoAncho = $maxWidth;
        } else {
            $nuevoAncho = ($maxHeight / $alto) * $ancho;
            $nuevoAlto = $maxHeight;
        }

        $nuevaImagen = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagecopyresampled($nuevaImagen, $icono, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        switch ($type) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($nuevaImagen);
                break;
            case 'png':
                imagepng($nuevaImagen);
                break;
            default:
                throw new Exception("Tipo de imagen no soportado para redimensionamiento.");
        }
        $imageData = ob_get_contents();
        ob_end_clean();

        imagedestroy($nuevaImagen);
        imagedestroy($icono);

        return $imageData;
    }

    private function resizeImageQuality($image, $type, $quality)
    {
        $icono = imagecreatefromstring($image);
        ob_start();
        switch ($type) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($icono, null, $quality);
                break;
            case 'png':
                $quality = 9 - round($quality / 10); // Convertir calidad JPEG a PNG (0-9)
                imagepng($icono, null, $quality);
                break;
            default:
                throw new Exception("Tipo de imagen no soportado para ajuste de calidad.");
        }
        $imageData = ob_get_contents();
        ob_end_clean();

        imagedestroy($icono);

        return $imageData;
    }

    private function resizeSvg($svgContent, $maxWidth, $maxHeight)
    {
        $dom = new DOMDocument();
        $dom->loadXML($svgContent);
        $svg = $dom->documentElement;

        // Obtener el ancho y alto original del SVG
        $width = $svg->getAttribute('width');
        $height = $svg->getAttribute('height');

        if ($width && $height) {
            // Redimensionar manteniendo la relación de aspecto
            if ($width > $height) {
                $newWidth = $maxWidth;
                $newHeight = ($maxWidth * $height) / $width;
            } else {
                $newHeight = $maxHeight;
                $newWidth = ($maxHeight * $width) / $height;
            }

            $svg->setAttribute('width', $newWidth);
            $svg->setAttribute('height', $newHeight);
        }

        return $dom->saveXML();
    }

    private function uploadToS3($image, $name, $type, $folder)
    {
        $s3 = new S3Client($this->BD->s3Options());
        $result = $s3->putObject([
            'Bucket' => $GLOBALS["AWS_BUCKET"],
            'Key' => $folder . $name . '.' . $type,
            'Body' => $image,
            'ContentType' => 'image/' . $type,
        ]);

        if (!$result) {
            throw new Exception("No se pudo guardar la imagen en S3.");
        }
    }
}
