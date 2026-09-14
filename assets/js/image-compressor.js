/**
 * =========================================================================
 * CIMS Client-Side Intelligent Image Compressor (HTML5 Canvas)
 * Downscales multi-megapixel camera photos (e.g. 12MP/8MB) to max 1920px Full HD
 * and applies perceptual 82% JPEG/WebP compression with zero visible loss.
 * Conforms to Quality Standards (Multi-device performance & HCI)
 * =========================================================================
 */

(function (window) {
    'use strict';

    /**
     * Intelligently compresses an image File or Blob before network transmission.
     * Leaves non-images (like PDFs) completely untouched.
     *
     * @param {File|Blob} file The selected file from an <input type="file">
     * @param {Object} options Configuration overrides
     * @param {number} [options.maxDimension=1920] Maximum width or height in pixels
     * @param {number} [options.quality=0.82] Compression quality (0.0 to 1.0)
     * @param {string} [options.outputType='image/jpeg'] Target MIME type ('image/jpeg' or 'image/webp')
     * @param {number} [options.skipIfUnderBytes=256000] Skip compression if file is already small (250KB)
     * @returns {Promise<File>} Compressed File object (or original if skipped/inapplicable)
     */
    window.compressImageFile = async function (file, options = {}) {
        if (!file) return file;

        const maxDimension = options.maxDimension || 1920;
        const quality = options.quality !== undefined ? options.quality : 0.82;
        const outputType = options.outputType || 'image/jpeg';
        const skipIfUnderBytes = options.skipIfUnderBytes !== undefined ? options.skipIfUnderBytes : 256000;

        // 1. Non-image bypass: PDFs and document attachments are left strictly untouched
        const mimeType = file.type || '';
        if (!mimeType.startsWith('image/')) {
            return file;
        }

        // 2. Small file bypass: If the image is already lightweight (<250KB), avoid unnecessary recompression
        if (file.size && file.size <= skipIfUnderBytes) {
            return file;
        }

        const startTime = performance.now();
        const originalSize = file.size;

        return new Promise((resolve) => {
            const reader = new FileReader();

            reader.onerror = () => {
                console.warn('[CIMS Compressor] FileReader failed, falling back to original file.');
                resolve(file);
            };

            reader.onload = (e) => {
                const img = new Image();

                img.onerror = () => {
                    console.warn('[CIMS Compressor] Image decode failed, falling back to original file.');
                    resolve(file);
                };

                img.onload = () => {
                    let width = img.naturalWidth || img.width;
                    let height = img.naturalHeight || img.height;

                    // Calculate proportional dimensions
                    if (width > maxDimension || height > maxDimension) {
                        if (width >= height) {
                            height = Math.round((height * maxDimension) / width);
                            width = maxDimension;
                        } else {
                            width = Math.round((width * maxDimension) / height);
                            height = maxDimension;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');

                    if (!ctx) {
                        resolve(file);
                        return;
                    }

                    // High-quality bicubic smoothing
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';

                    // For JPEG, fill white background to avoid black background on transparent PNGs
                    if (outputType === 'image/jpeg') {
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, width, height);
                    }

                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(
                        (blob) => {
                            if (!blob || blob.size >= originalSize) {
                                // If compressed file isn't smaller, preserve original
                                resolve(file);
                                return;
                            }

                            const duration = Math.round(performance.now() - startTime);
                            const origKb = Math.round(originalSize / 1024);
                            const newKb = Math.round(blob.size / 1024);
                            const savings = Math.round((1 - blob.size / originalSize) * 100);

                            console.info(
                                `[CIMS Compressor] Optimized "${file.name || 'image'}": ${origKb}KB -> ${newKb}KB (${savings}% smaller) in ${duration}ms`
                            );

                            const newFileName = file.name
                                ? file.name.replace(/\.[^.]+$/, outputType === 'image/webp' ? '.webp' : '.jpg')
                                : 'optimized_photo.jpg';

                            const compressedFile = new File([blob], newFileName, {
                                type: outputType,
                                lastModified: Date.now()
                            });

                            resolve(compressedFile);
                        },
                        outputType,
                        quality
                    );
                };

                img.src = e.target.result;
            };

            reader.readAsDataURL(file);
        });
    };

    /**
     * Compresses a Base64 dataURL (such as from camera snapshots) to max 1920px Full HD.
     *
     * @param {string} dataUrl Base64 dataURL (data:image/...)
     * @param {Object} options Configuration overrides
     * @returns {Promise<string>} Compressed Base64 dataURL
     */
    window.compressImageBase64 = async function (dataUrl, options = {}) {
        if (!dataUrl || typeof dataUrl !== 'string' || !dataUrl.startsWith('data:image/')) {
            return dataUrl;
        }

        const maxDimension = options.maxDimension || 1920;
        const quality = options.quality !== undefined ? options.quality : 0.82;
        const outputType = options.outputType || 'image/jpeg';

        return new Promise((resolve) => {
            const img = new Image();
            img.onerror = () => resolve(dataUrl);
            img.onload = () => {
                let width = img.naturalWidth || img.width;
                let height = img.naturalHeight || img.height;

                if (width > maxDimension || height > maxDimension) {
                    if (width >= height) {
                        height = Math.round((height * maxDimension) / width);
                        width = maxDimension;
                    } else {
                        width = Math.round((width * maxDimension) / height);
                        height = maxDimension;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    resolve(dataUrl);
                    return;
                }

                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                if (outputType === 'image/jpeg') {
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, width, height);
                }

                ctx.drawImage(img, 0, 0, width, height);
                const compressedDataUrl = canvas.toDataURL(outputType, quality);
                resolve(compressedDataUrl.length < dataUrl.length ? compressedDataUrl : dataUrl);
            };
            img.src = dataUrl;
        });
    };
})(window);
