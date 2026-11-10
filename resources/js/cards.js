/**
 * "Share your week": share the card image through the phone's share sheet (Web Share with a file), or download it.
 * The PNG comes from the server when it can draw one; otherwise the browser draws it from the card's SVG. On desktop
 * without Web Share, the Share button stays hidden and Download does the job.
 */
import { eachOnPage } from './shell';

function activePanel(root) {
    return [...root.querySelectorAll('[data-card-size]')].find((panel) => !panel.hidden) ?? root.querySelector('[data-card-size]');
}

/**
 * Draw the inline SVG onto a canvas at full size and return a PNG blob.
 */
function pngFromSvg(svg) {
    const width = Number(svg.getAttribute('width'));
    const height = Number(svg.getAttribute('height'));
    const markup = new XMLSerializer().serializeToString(svg);
    const url = URL.createObjectURL(new Blob([markup], { type: 'image/svg+xml' }));

    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(image, 0, 0, width, height);
            URL.revokeObjectURL(url);
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('No image'))), 'image/png');
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('No image'));
        };
        image.src = url;
    });
}

async function cardPng(root) {
    const panel = activePanel(root);

    if (root.dataset.cardPng) {
        const response = await fetch(panel.dataset.cardPngUrl, { credentials: 'same-origin' });

        if (response.ok) {
            return response.blob();
        }
    }

    return pngFromSvg(panel.querySelector('svg'));
}

function fileName(root) {
    const size = activePanel(root).dataset.cardSize;

    return (root.dataset.cardName || 'goalline-week.png').replace(/\.png$/, `-${size}.png`);
}

function say(root, message) {
    const status = root.querySelector('[data-card-status]');

    if (status) {
        status.textContent = message;
    }
}

eachOnPage('[data-result-card]', (root) => {
    const shareButton = root.querySelector('[data-card-share]');
    const download = root.querySelector('[data-card-download]');
    const probe = new File([''], 'card.png', { type: 'image/png' });

    if (shareButton && navigator.share && navigator.canShare?.({ files: [probe] })) {
        shareButton.hidden = false;
    }

    shareButton?.addEventListener('click', async () => {
        shareButton.disabled = true;
        say(root, 'Getting your card ready…');

        try {
            const file = new File([await cardPng(root)], fileName(root), { type: 'image/png' });
            await navigator.share({ files: [file], title: 'My GoalLine week', text: root.dataset.cardTitle });
            say(root, '');
        } catch (error) {
            // Closing the share sheet isn't a problem.
            say(root, error?.name === 'AbortError' ? '' : 'Sharing didn’t work here. Download the image instead.');
        } finally {
            shareButton.disabled = false;
        }
    });

    // Download: the server's PNG when it has one (a plain link), otherwise one drawn here.
    download?.addEventListener('click', async (event) => {
        const panel = activePanel(root);

        if (root.dataset.cardPng) {
            download.href = panel.dataset.cardPngUrl;
            download.download = fileName(root);

            return;
        }

        event.preventDefault();

        try {
            const url = URL.createObjectURL(await cardPng(root));
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName(root);
            document.body.append(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch {
            window.location.href = panel.dataset.cardSvgUrl;
        }
    });
});
