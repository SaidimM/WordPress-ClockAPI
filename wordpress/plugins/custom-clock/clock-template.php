<?php
// Get configured Unsplash topics from plugin settings
$pwc_options = get_option('pwc_options', array());
$unsplash_topics = isset($pwc_options['unsplash_topics']) ? $pwc_options['unsplash_topics'] : 'nature,landscape';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Minimal digital clock with dynamic backgrounds">
    <title>Clock - Saidim.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Orbitron', monospace, sans-serif;
            overflow: hidden;
            width: 100vw;
            height: 100vh;
            position: relative;
        }

        /* Background slideshow */
        .background-slideshow {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            transform: scale(1);
            transition: opacity 2s ease-in-out, transform 15s ease-out;
        }

        .background-image.active {
            opacity: 1;
            transform: scale(1.1);
        }

        /* Alternative animation classes for variety */
        .background-image.zoom-in {
            transform: scale(1);
        }

        .background-image.zoom-in.active {
            transform: scale(1.15);
        }

        .background-image.pan-left {
            transform: scale(1.1) translateX(0);
        }

        .background-image.pan-left.active {
            transform: scale(1.1) translateX(-30px);
        }

        .background-image.pan-right {
            transform: scale(1.1) translateX(0);
        }

        .background-image.pan-right.active {
            transform: scale(1.1) translateX(30px);
        }

        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.3) 0%, rgba(0, 0, 0, 0.5) 100%);
            z-index: 1;
        }

        /* Content container */
        .content-container {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #ffffff;
            text-align: center;
            padding: 40px;
        }

        /* Digital clock with individual digits */
        .digital-clock {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: clamp(8px, 1.5vw, 20px);
            margin-bottom: 40px;
        }

        .digit-group {
            display: flex;
            gap: clamp(4px, 0.8vw, 10px);
        }

        .digit {
            position: relative;
            width: clamp(60px, 12vw, 150px);
            height: clamp(90px, 18vw, 220px);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .digit-value {
            font-size: clamp(80px, 15vw, 200px);
            font-weight: 900;
            line-height: 1;
            position: absolute;
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }

        .digit-value.fade-out {
            opacity: 0;
            transform: translateY(-20px);
        }

        .digit-value.fade-in {
            opacity: 1;
            transform: translateY(0);
        }

        .separator {
            font-size: clamp(80px, 15vw, 200px);
            font-weight: 900;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            height: clamp(90px, 18vw, 220px);
        }

        /* Date display */
        .date-display {
            font-size: clamp(20px, 3vw, 36px);
            font-weight: 400;
            opacity: 0.95;
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.6);
            letter-spacing: 0.05em;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Bottom info container */
        .bottom-info {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 3;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* Photo credit */
        .photo-credit {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.85);
            transition: background 0.3s ease;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            line-height: 1;
            height: 34px;
        }

        .photo-credit:hover {
            background: rgba(0, 0, 0, 0.6);
        }

        .photo-credit a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
        }

        .photo-credit a:hover {
            text-decoration: underline;
        }

        /* Download button */
        .download-button {
            opacity: 0;
            transform: translateX(-15px);
            transition: all 0.4s ease;
            pointer-events: none;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            padding: 10px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            height: 34px;
        }

        .bottom-info:hover .download-button {
            opacity: 1;
            transform: translateX(0);
            pointer-events: all;
        }

        .download-button:hover {
            background: rgba(0, 0, 0, 0.6);
        }

        .download-link {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: color 0.3s ease;
            line-height: 1;
            width: 100%;
            height: 100%;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .download-link:hover {
            color: #ffffff;
        }

        .download-link.downloading {
            opacity: 0.6;
            cursor: wait;
        }

        .download-icon {
            width: 14px;
            height: 14px;
            display: inline-block;
        }

        .download-icon.spinning {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-container > * {
            animation: fadeIn 1.2s ease-out;
        }

        @media (max-width: 768px) {
            .bottom-info {
                bottom: 15px;
                left: 15px;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .photo-credit {
                font-size: 9px;
                padding: 8px 10px;
                height: 28px;
            }

            .download-button {
                padding: 8px 10px;
                transform: translateY(-10px);
                height: 28px;
            }

            .bottom-info:hover .download-button {
                transform: translateY(0);
            }

            .download-link {
                font-size: 9px;
            }

            .download-icon {
                width: 12px;
                height: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="background-slideshow" id="slideshow">
        <div class="background-image active" id="bg1"></div>
        <div class="background-image" id="bg2"></div>
        <div class="background-overlay"></div>
    </div>

    <div class="bottom-info">
        <div class="photo-credit">
            Photo by&nbsp;<a href="https://unsplash.com" target="_blank" id="photographer-link">Unsplash Contributors</a>&nbsp;on&nbsp;<a href="https://unsplash.com" target="_blank" id="unsplash-link">Unsplash</a>
        </div>
        <div class="download-button">
            <button class="download-link" id="download-button" type="button">
                <svg class="download-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 12a1 1 0 01-.707-.293l-4-4a1 1 0 011.414-1.414L9 8.586V3a1 1 0 112 0v5.586l2.293-2.293a1 1 0 111.414 1.414l-4 4A1 1 0 0110 12z"/>
                    <path d="M3 14a1 1 0 011 1v1h12v-1a1 1 0 112 0v2a1 1 0 01-1 1H3a1 1 0 01-1-1v-2a1 1 0 011-1z"/>
                </svg>
                Download
            </button>
        </div>
    </div>

    <div class="content-container">
        <div class="digital-clock" id="digital-clock">
            <div class="digit-group">
                <div class="digit" id="hour1"><span class="digit-value fade-in">0</span></div>
                <div class="digit" id="hour2"><span class="digit-value fade-in">0</span></div>
            </div>
            <div class="separator">:</div>
            <div class="digit-group">
                <div class="digit" id="minute1"><span class="digit-value fade-in">0</span></div>
                <div class="digit" id="minute2"><span class="digit-value fade-in">0</span></div>
            </div>
            <div class="separator">:</div>
            <div class="digit-group">
                <div class="digit" id="second1"><span class="digit-value fade-in">0</span></div>
                <div class="digit" id="second2"><span class="digit-value fade-in">0</span></div>
            </div>
        </div>

        <div class="date-display" id="date-display">Loading...</div>
    </div>

    <script>
        // Configuration
        const SLIDE_INTERVAL = 15000; // 15 seconds per image
        const IMAGE_LOAD_TIMEOUT = 3000; // 3 seconds max to load images before fallback
        const UNSPLASH_TOPICS = <?php echo json_encode($unsplash_topics); ?>; // Topics from WordPress settings
        let currentImageIndex = 0;
        let images = [];
        let currentImage = null;
        let useGradientFallback = false; // Track if we should skip to gradients
        const animationTypes = ['zoom-in', 'pan-left', 'pan-right'];

        // Fetch images from Clock API
        async function fetchUnsplashImages() {
            try {
                // Call Clock API endpoint with configured topics
                const siteUrl = window.location.origin;
                const response = await fetch(`${siteUrl}/api/clock/images?count=10&query=${encodeURIComponent(UNSPLASH_TOPICS)}`);

                if (response.ok) {
                    const data = await response.json();

                    // Check if we got images
                    if (data.success && data.images && data.images.length > 0) {
                        images = data.images;
                        console.log(`Loaded ${images.length} images from Clock API (cached: ${data.cached})`);
                    } else {
                        console.log('Using fallback images - no images returned');
                        useFallbackImages();
                    }
                } else {
                    console.log('Using fallback images - API error');
                    useFallbackImages();
                }
            } catch (error) {
                console.log('Using fallback images - fetch error:', error);
                useFallbackImages();
            }

            // Set first background
            if (images.length > 0) {
                setBackgroundImage(0);
            }
        }

        // Fallback images - using beautiful gradients (instant loading, no external dependencies)
        function useFallbackImages() {
            // Beautiful gradient backgrounds that load instantly
            const gradients = [
                'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
                'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
                'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
                'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
                'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)',
                'linear-gradient(135deg, #ff6e7f 0%, #bfe9ff 100%)'
            ];
            images = gradients.map((gradient, index) => ({
                url: gradient,
                downloadUrl: null, // Gradients can't be downloaded
                photographer: 'Gradient Design',
                photographerUrl: 'https://saidim.com',
                isGradient: true
            }));
        }

        // Track image view
        async function trackImageView(image) {
            try {
                const siteUrl = window.location.origin;
                await fetch(`${siteUrl}/api/clock/track/view`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Platform': 'web'
                    },
                    body: JSON.stringify({
                        imageId: image.id || 'unknown',
                        photographer: image.photographer,
                        photographerUrl: image.photographerUrl
                    })
                });
            } catch (error) {
                // Silent fail - don't disrupt user experience
                console.log('View tracking failed:', error);
            }
        }

        // Set background image with random animation effect
        function setBackgroundImage(index) {
            if (images.length === 0) return;

            const bg1 = document.getElementById('bg1');
            const bg2 = document.getElementById('bg2');
            const activeEl = document.querySelector('.background-image.active');
            const inactiveEl = activeEl === bg1 ? bg2 : bg1;

            const image = images[index];
            currentImage = image; // Track current image

            // Remove all animation classes from inactive element
            animationTypes.forEach(type => inactiveEl.classList.remove(type));

            // Add random animation type
            const randomAnimation = animationTypes[Math.floor(Math.random() * animationTypes.length)];
            inactiveEl.classList.add(randomAnimation);

            // Check if it's a gradient (instant) or image URL (needs preloading)
            if (image.isGradient) {
                // Gradients load instantly, no preloading needed
                inactiveEl.style.backgroundImage = image.url;

                // Fade transition
                setTimeout(() => {
                    activeEl.classList.remove('active');
                    inactiveEl.classList.add('active');

                    // Track view when background becomes visible
                    trackImageView(image);
                }, 100);

                // Update photo credit
                const linkEl = document.getElementById('photographer-link');
                linkEl.textContent = image.photographer;
                linkEl.href = image.photographerUrl;

                // Hide Unsplash link for gradients
                const unsplashLinkEl = document.getElementById('unsplash-link');
                if (unsplashLinkEl) {
                    unsplashLinkEl.style.display = 'none';
                }
            } else {
                // Preload image before setting it
                const img = new Image();
                img.onload = () => {
                    // Set background image only after it's loaded
                    inactiveEl.style.backgroundImage = `url('${image.url}')`;

                    // Fade transition
                    setTimeout(() => {
                        activeEl.classList.remove('active');
                        inactiveEl.classList.add('active');

                        // Track view when image becomes visible
                        trackImageView(image);
                    }, 100);

                    // Update photo credit with UTM parameters
                    const linkEl = document.getElementById('photographer-link');
                    linkEl.textContent = image.photographer;
                    linkEl.href = image.photographerUrl;

                    // Update Unsplash link with UTM parameters
                    const unsplashLinkEl = document.getElementById('unsplash-link');
                    if (unsplashLinkEl && image.unsplashUrl) {
                        unsplashLinkEl.style.display = '';
                        unsplashLinkEl.href = image.unsplashUrl;
                    }

                    // Log attribution URLs for verification (helps with Unsplash Production approval)
                    console.log('Image Attribution:');
                    console.log('- Photographer:', image.photographer);
                    console.log('- Photographer URL:', image.photographerUrl);
                    console.log('- Unsplash URL:', image.unsplashUrl);

                    // Preload next two images for smoother experience
                    const nextIndex = (index + 1) % images.length;
                    const nextNextIndex = (index + 2) % images.length;

                    if (images[nextIndex] && !images[nextIndex].isGradient) {
                        const nextImg = new Image();
                        nextImg.src = images[nextIndex].url;
                    }

                    if (images[nextNextIndex] && !images[nextNextIndex].isGradient) {
                        const nextNextImg = new Image();
                        nextNextImg.src = images[nextNextIndex].url;
                    }
                };

                img.onerror = () => {
                    console.error('Failed to load image:', image.url);
                    // Fallback: use gradients instead
                    console.log('Switching to gradient fallback...');
                    useFallbackImages();
                    if (images.length > 0) {
                        setBackgroundImage(0);
                    }
                };

                img.src = image.url;
            }
        }

        // Start slideshow
        function startSlideshow() {
            setInterval(() => {
                currentImageIndex = (currentImageIndex + 1) % images.length;
                setBackgroundImage(currentImageIndex);
            }, SLIDE_INTERVAL);
        }

        // Update individual digit with fade effect
        function updateDigit(elementId, newValue) {
            const element = document.getElementById(elementId);
            const currentSpan = element.querySelector('.digit-value');
            const currentValue = currentSpan.textContent;

            if (currentValue !== newValue) {
                // Fade out current value
                currentSpan.classList.remove('fade-in');
                currentSpan.classList.add('fade-out');

                // Create new span with new value
                setTimeout(() => {
                    currentSpan.textContent = newValue;
                    currentSpan.classList.remove('fade-out');
                    currentSpan.classList.add('fade-in');
                }, 150);
            }
        }

        // Update clock
        function updateClock() {
            const now = new Date();
            
            // Get time components
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            // Update each digit individually with fade effect
            updateDigit('hour1', hours[0]);
            updateDigit('hour2', hours[1]);
            updateDigit('minute1', minutes[0]);
            updateDigit('minute2', minutes[1]);
            updateDigit('second1', seconds[0]);
            updateDigit('second2', seconds[1]);
            
            // Update date display
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            document.getElementById('date-display').textContent = now.toLocaleDateString('en-US', options);
        }

        // Track image download
        async function trackImageDownload(image) {
            try {
                const siteUrl = window.location.origin;
                await fetch(`${siteUrl}/api/clock/track/download`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Platform': 'web'
                    },
                    body: JSON.stringify({
                        imageId: image.id || 'unknown',
                        photographer: image.photographer,
                        photographerUrl: image.photographerUrl
                    })
                });
            } catch (error) {
                // Silent fail - don't disrupt user experience
                console.log('Download tracking failed:', error);
            }
        }

        // Download current image
        async function downloadCurrentImage() {
            if (!currentImage || !currentImage.downloadUrl || currentImage.isGradient) {
                console.log('No downloadable image available');
                return;
            }

            const button = document.getElementById('download-button');

            // Disable button to prevent multiple clicks
            button.disabled = true;

            try {
                // Log current image details for verification
                console.log('=== Download Button Pressed ===');
                console.log('Current Image Details:', {
                    id: currentImage.id,
                    unsplashId: currentImage.unsplashId,
                    photographer: currentImage.photographer,
                    downloadLocation: currentImage.downloadLocation,
                    hasDownloadLocation: !!currentImage.downloadLocation
                });

                // Track download (our analytics)
                trackImageDownload(currentImage);

                // Trigger Unsplash download tracking (required by Unsplash API guidelines)
                if (currentImage.downloadLocation) {
                    try {
                        const siteUrl = window.location.origin;
                        console.log('✓ Triggering Unsplash download endpoint:', currentImage.downloadLocation);
                        const trackingResponse = await fetch(`${siteUrl}/api/clock/images/unsplash-download`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                downloadLocation: currentImage.downloadLocation
                            })
                        });
                        const responseData = await trackingResponse.json();
                        console.log('✓ Unsplash download endpoint triggered successfully');
                        console.log('  Response status:', trackingResponse.status);
                        console.log('  Response data:', responseData);
                    } catch (error) {
                        console.log('⚠ Unsplash tracking failed (non-blocking):', error);
                    }
                } else {
                    console.log('⚠ No downloadLocation available for Unsplash tracking');
                    console.log('  Current image object:', currentImage);
                }

                // Generate filename from photographer name or use timestamp
                const filename = `${currentImage.photographer.replace(/\s+/g, '_')}_${Date.now()}.jpg`;

                // Use local cached image for instant download (much faster than fetching from Unsplash)
                const siteUrl = window.location.origin;
                // Check if image is cached (starts with /cache/images/)
                let downloadUrl;
                if (currentImage.cached || currentImage.downloadUrl.startsWith('/cache/images/')) {
                    // Download directly from nginx-served cached file (fast!)
                    downloadUrl = `${siteUrl}${currentImage.downloadUrl}`;
                    console.log('✓ Downloading from local cache (fast)');
                } else {
                    // Fallback: use proxy for non-cached images
                    downloadUrl = `${siteUrl}/api/clock/images/download?url=${encodeURIComponent(currentImage.downloadUrl)}&filename=${encodeURIComponent(filename)}`;
                    console.log('Downloading via proxy (slower)');
                }

                // Create link and trigger download immediately
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = filename;

                // Trigger download
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);

                // Reset button state after a short delay
                setTimeout(() => {
                    button.disabled = false;
                }, 500);

            } catch (error) {
                console.error('Download failed:', error);

                // Reset button state
                button.disabled = false;

                // Fallback: open in new tab
                window.open(currentImage.downloadUrl, '_blank');
            }
        }

        // Add download button event listener
        document.getElementById('download-button').addEventListener('click', downloadCurrentImage);

        // Double-tap/double-click to toggle fullscreen
        let lastTapTime = 0;
        const DOUBLE_TAP_DELAY = 300; // milliseconds

        function toggleFullscreen() {
            if (!document.fullscreenElement &&
                !document.webkitFullscreenElement &&
                !document.mozFullScreenElement &&
                !document.msFullscreenElement) {
                // Enter fullscreen
                const elem = document.documentElement;
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen(); // Safari
                } else if (elem.mozRequestFullScreen) {
                    elem.mozRequestFullScreen(); // Firefox
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen(); // IE/Edge
                }
                console.log('Entering fullscreen mode');
            } else {
                // Exit fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
                console.log('Exiting fullscreen mode');
            }
        }

        // Handle double-click (desktop)
        document.body.addEventListener('dblclick', (e) => {
            // Don't trigger if clicking on links or buttons
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            toggleFullscreen();
        });

        // Handle double-tap (mobile)
        document.body.addEventListener('touchend', (e) => {
            // Don't trigger if tapping on links or buttons
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                return;
            }

            const currentTime = new Date().getTime();
            const tapInterval = currentTime - lastTapTime;

            if (tapInterval < DOUBLE_TAP_DELAY && tapInterval > 0) {
                // Double tap detected
                e.preventDefault(); // Prevent zoom on double-tap
                toggleFullscreen();
                lastTapTime = 0; // Reset
            } else {
                lastTapTime = currentTime;
            }
        });

        // Initialize
        updateClock();
        setInterval(updateClock, 1000);
        fetchUnsplashImages();
        startSlideshow();
    </script>
</body>
</html>
