<?php

# vars
$lib = $modx->getOption("lib", $scriptProperties, "Imagick");
$libPath = $modx->getOption("libPath", $scriptProperties, "/Applications/MAMP/Library/bin/convert");
$mode = $modx->getOption("mode", $scriptProperties, "");
$type = $modx->getOption("type", $scriptProperties, "image");
$input = $modx->getOption("input", $scriptProperties, "");
$sizes = $modx->getOption("options", $scriptProperties, "");
$quality = $modx->getOption("quality", $scriptProperties, 70);
$fileExtension = $modx->getOption("fileExtension", $scriptProperties, "webp");
$setRatio = $modx->getOption("setRatio", $scriptProperties, true);
$cachePath = $modx->getOption("cachePath", $scriptProperties, "assets/image-cache/");
$cultureKey = $modx->getOption("cultureKey");
$basePath = $modx->getOption("base_path");
$filePath = preg_replace("/^\/?" . $cultureKey . "\//i", "", $input);
$filePathLast = $filePath;
$filePatheIsRemote = str_starts_with($filePath, 'https');
$srcsets = [];
$src = [];

# fall back to GD if Imagick is not installed
if ($lib == "Imagick" && !class_exists("Imagick")) {
    $lib = "gd";
}

# resize with GD, crop like Imagick::cropThumbnailImage() if width and height are set
$resizeWithGd = function ($source, $target, $width, $height, $quality, $fileExtension) {
    $image = @imagecreatefromstring(file_get_contents($source));
    if ($image === false) {
        throw new \RuntimeException("GD can't read " . $source);
    }

    $sourceWidth = imagesx($image);
    $sourceHeight = imagesy($image);
    $width = (int) $width;
    $height = (int) $height;
    $cropX = 0;
    $cropY = 0;
    $cropWidth = $sourceWidth;
    $cropHeight = $sourceHeight;

    if ($width && $height) {
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);
        $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
        $cropY = (int) round(($sourceHeight - $cropHeight) / 2);
    } elseif ($width) {
        $height = (int) round($sourceHeight * $width / $sourceWidth);
    } elseif ($height) {
        $width = (int) round($sourceWidth * $height / $sourceHeight);
    } else {
        $width = $sourceWidth;
        $height = $sourceHeight;
    }

    $resized = imagecreatetruecolor($width, $height);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
    imagecopyresampled($resized, $image, 0, 0, $cropX, $cropY, $width, $height, $cropWidth, $cropHeight);

    switch (strtolower($fileExtension)) {
        case "webp":
            $saved = imagewebp($resized, $target, (int) $quality);
            break;
        case "jpg":
        case "jpeg":
            $saved = imagejpeg($resized, $target, (int) $quality);
            break;
        case "png":
            $saved = imagepng($resized, $target);
            break;
        default:
            throw new \RuntimeException("GD can't write " . $fileExtension);
    }

    if (!$saved) {
        throw new \RuntimeException("GD can't write " . $target);
    }
};

# if svg return
if (function_exists("mime_content_type") && @mime_content_type($basePath . $filePath) === "image/svg+xml") {
    return '="' . $input . '" width="1" height="1"';
}

# if remote image
if ($filePatheIsRemote) {
    $basePath = '';
}

# original image not exists
if (empty($filePath) || ( !file_exists($basePath . $filePath) && !$filePatheIsRemote )) {
    if ($mode == "json") {
        return "'" . $input . "'";
    }
    if (!empty($input)) {
        return '="' . $input . '"';
    }
    return false;
}

# loop sizes
$sizes = explode(",", $sizes);
natsort($sizes);
foreach (array_reverse($sizes) as $size) {
    $dimensions = explode("x", $size);
    $width = isset($dimensions[0]) ? $dimensions[0] : "";
    $height = isset($dimensions[1]) ? $dimensions[1] : "";
    $filePathInfo = pathinfo($filePath);
    $savePathFilename = $modx->filterPathSegment($filePathInfo["filename"]);
    $savePathExtension = "." . substr(md5($filePathLast), 0, 8) . "." . $width . "x" . $height . "-" . $quality . "." . $fileExtension;
    $savePath = $cachePath . $savePathFilename . $savePathExtension;
   
    # check if base image exists
    if (!file_exists($basePath . $filePathLast) || filesize($basePath . $filePathLast) < 1) {
        $filePathLast = $filePath;
    }
   
    # cached image not found
    if (!file_exists($basePath . $savePath)) {
        # check if cache dir exists
        if (!is_dir($basePath . $cachePath)) {
            mkdir($basePath . $cachePath, 0755);
        } else {

            # cleanup cache
            foreach (glob($basePath . $cachePath . "*." . $fileExtension) as $file) {
                if (filemtime($file) < time() - (60 * 60 * 24 * 30)) {
                    unlink($file);
                }
            }

        }

        # generate image, on error log it and output the original image
        try {
            if ($lib == "cli") {

                if ($type == "video") {

                    $cmd = $libPath . " -ss 00:00:00 -i '" . $basePath . $filePathLast . "' -filter:v scale=\"" . ( $width ?? "-1" ) . ":" . ( $height ?? "-1" ) . "\" " . $basePath . $savePath;

                } else {

                    $resize = "-resize " . ( $width ?? "" ) . "x" . ( $height ?? "" );

                    if ($width && $height) {
                        $resize = "-resize " . $width . "x" . $height . "^ -gravity center -extent " . $width . "x" . $height;
                    }

                    $cmd = "export OMP_NUM_THREADS=1;"; // bugfixes IONOS resize large images, thanks to https://stackoverflow.com/a/69133237
                    $cmd .= $libPath . " '" . $basePath . $filePathLast . "' " . $resize . "  -quality " . $quality . " '" . $basePath . $savePath . "'";

                }

                shell_exec($cmd);

            } elseif ($lib == "gd") {

                $resizeWithGd($basePath . $filePathLast, $basePath . $savePath, $width, $height, $quality, $fileExtension);

            } else {

                $image = new Imagick($basePath . $filePathLast);

                if ($width && $height) {
                    $image->cropThumbnailImage((int) $width, (int) $height);
                } else {
                    $image->thumbnailImage((int) $width, (int) $height);
                }

                $image->setImageCompressionQuality($quality);
                $image->writeImage($basePath . $savePath);

            }
        } catch (\Throwable $e) {
            $modx->log($modx::LOG_LEVEL_ERROR, "[resizeImage] " . $input . ": " . $e->getMessage());
            break;
        }

    }
        
    # get generated image    
    if (file_exists($basePath . $savePath)) {
        list($width, $height) = getimagesize($basePath . $savePath);
        $srcsets[] = [
            "url" => $savePath,
            "width" => $width,
            "height" => $height
        ];
        
        # use image for next loop
        $filePathLast = $savePath;
    }
}

# no srcsets, maybe video is converting in the background i.e. or the image could not be generated
if (!count($srcsets)) {
    if ($mode == "json") {
        return "'" . $input . "'";
    }
    return '="' . $input . '"';
}

# generate srcset
foreach ($srcsets as $srcset) {
    $src[] = $srcset["url"] . " " . $srcset["width"] ."w";
}

# set width and height ratio
$width = $srcsets[0]["width"];
$height = $srcsets[0]["height"];

# output as json
if ($mode == "json") {
    return json_encode([
        "url" => $input,
        "src" => implode(",", $src),
        "width" => $width ?? 0,
        "height" => $height ?? 0
    ]);
}

# output as base64
$srcs = implode(",", $src);
if ($mode == "base64") {
    $srcs = "data:image/" . $fileExtension . ";base64," . base64_encode(file_get_contents($srcsets[0]["url"]));
}

$o = '="' . $srcs . '" width="' . $width . '" height="' . $height . '"';

if ($setRatio) {
    $o .= ' style="aspect-ratio: ' . $width . '/' . $height . '"';
}

return $o;
