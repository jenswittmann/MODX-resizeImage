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
$srcsets = [];
$src = [];

# if svg return
if (mime_content_type($basePath . $filePath) === "image/svg+xml") {
    return '="' . $input . '" width="1" height="1"';
}

# original image not exists
if (!file_exists($basePath . $filePath)) {
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
    
        # generate image
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

# no srcsets, maybe video is converting in the background i.e.
if (!count($srcsets)) {
    return $input;
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
    return "{
        url: '" . $input . "',
        src: '" . implode(",", $src) . "',
        width: " . ( $width ?? 0 ) . ",
        height: " . ( $height ?? 0 ) . "
    }";
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
