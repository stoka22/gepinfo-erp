import Hls from 'hls.js';

document.querySelectorAll('.camera-video').forEach((video) => {
    const src = video.dataset.src;
    if (!src) return;

    if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(src);
        hls.attachMedia(video);
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = src;
    }
});
