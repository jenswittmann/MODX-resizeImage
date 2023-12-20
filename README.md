# MODX `[[!resizeImage]]` Snippet (WIP 🧑‍🏭)

Snippet for resizing Images in MODX. It replaces [pThumb](https://github.com/modxcms/pThumb) in 99% of my projects, but can be used in parallel 😸

✅ Generates WebP  
✅ Output srcset and aspect ratio for better web vitals  
✅ Can use binary libs like `convert` (Imagine workaround on IONOS) and `ffmpeg` (for Videothumbnails)  
✅ Output as base64 for Lazyloading Placeholders

## Options

https://github.com/jenswittmann/MODX-resizeImage/blob/9f0cc0b3fe414526e18f46cfe6ee1f92f43e01a7/resizeimage.snippet.php#L4-L13

## Examples

### WebP Images as `srcset`

```
<img
    data-srcset[[!resizeImage?
        &input=`[[+url]]`
        &options=`2000,1250,800` ]]
    alt=""
/>
```

Generates this:

```
<img
  data-srcset="
    assets/image-cache/dummy1.6bee3066.2000x-70.webp 2000w,
    assets/image-cache/dummy1.143b6e11.1250x-70.webp 1250w,
    assets/image-cache/dummy1.b23222b6.800x-70.webp 800w
  "
  width="2000"
  height="1193"
  style="aspect-ratio: 2000/1193"
  alt=""
/>
```

ℹ️ Allways call the Snippet uncached, to prevent missing images when old images get deleted by the Snippet themself.

### Lazyload base64 Placeholder

```
<img
    src[[!resizeImage?
        &input=`[[+url]]`
        &options=`100`
        &lib=`cli`                    
        &libPath=`[[++ffmpeg.binPath]]`
        &type=`video`
        &fileExtension=`png`
        &mode=`base64` ]]
    alt=""
/>
```

## Roadmap

- [ ] Test and get Feedback
- [ ] Make it avaible as MODX Package
