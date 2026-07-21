import { GlobalWorkerOptions, getDocument } from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

async function renderPdfThumbnail(url, canvas) {
    try {
        const pdf = await getDocument({ url }).promise;
        const page = await pdf.getPage(1);

        const unscaledViewport = page.getViewport({ scale: 1 });
        const scale = canvas.width / unscaledViewport.width;
        const viewport = page.getViewport({ scale });

        canvas.width = viewport.width;
        canvas.height = viewport.height;

        const context = canvas.getContext('2d');
        await page.render({ canvasContext: context, viewport }).promise;
    } catch (error) {
        console.error('Falha ao renderizar a miniatura do PDF', url, error);
    }
}

// Vanilla JS + MutationObserver instead of Alpine x-init: this script loads
// as a deferred ES module, so it can finish loading *after* Alpine has
// already processed the initial DOM — an x-init calling a not-yet-defined
// window function would silently fail. Scanning for canvases ourselves,
// both on load and whenever Livewire adds new ones (pagination/filtering),
// sidesteps that race entirely.
function hydratePendingCanvases(root) {
    root.querySelectorAll('canvas[data-pdf-preview-url]:not([data-pdf-preview-rendered])').forEach((canvas) => {
        canvas.setAttribute('data-pdf-preview-rendered', '1');
        renderPdfThumbnail(canvas.dataset.pdfPreviewUrl, canvas);
    });
}

hydratePendingCanvases(document);

new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
            if (!(node instanceof HTMLElement)) {
                continue;
            }

            if (node.matches?.('canvas[data-pdf-preview-url]')) {
                hydratePendingCanvases(node.parentElement ?? document);
            } else {
                hydratePendingCanvases(node);
            }
        }
    }
}).observe(document.body, { childList: true, subtree: true });
