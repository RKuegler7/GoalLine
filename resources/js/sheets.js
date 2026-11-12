/**
 * Bottom sheets (task details, Check in, Start): they close on Back (the phone's Back, the iPhone edge swipe, or the
 * browser button), on a swipe down, on a tap outside, or with their X, and never leave the page.
 *
 * Opening a sheet adds one history entry. Back pops it: we catch that popstate before Turbo sees it (a capturing
 * listener on window runs first) and just close the sheet. Closing it any other way takes the entry back off, quietly.
 */

let entryFor = null; // The open sheet that owns the extra history entry.
let swallowNextPop = false; // Our own history.back() after a close: Turbo must not treat it as a visit.

window.addEventListener('popstate', (event) => {
    if (swallowNextPop) {
        swallowNextPop = false;
        event.stopImmediatePropagation();

        return;
    }

    if (entryFor?.open) {
        event.stopImmediatePropagation();
        const sheet = entryFor;
        entryFor = null;
        sheet.close();
    }
}, { capture: true });

function onClose(event) {
    const sheet = event.currentTarget;

    if (entryFor === sheet) {
        entryFor = null;
        swallowNextPop = true;
        history.back();
    }
}

/**
 * Swipe down on the sheet (from its top, or anywhere when its content is scrolled to the top) to close it.
 */
function watchSwipe(sheet) {
    let startY = null;

    sheet.addEventListener('touchstart', (event) => {
        const scroller = event.target.closest('[data-task-sheet-body], [data-sheet-scroll]') ?? sheet;
        startY = scroller.scrollTop > 0 ? null : event.touches[0].clientY;
    }, { passive: true });

    sheet.addEventListener('touchend', (event) => {
        if (startY !== null && event.changedTouches[0].clientY - startY > 90) {
            sheet.close();
        }
        startY = null;
    }, { passive: true });
}

/**
 * Open a sheet (a <dialog>) as a modal with Back-to-close and swipe-down-to-close.
 */
export function openSheet(sheet) {
    if (!sheet || typeof sheet.showModal !== 'function' || sheet.open) {
        return;
    }

    if (!sheet.dataset.sheetReady) {
        sheet.dataset.sheetReady = '1';
        sheet.addEventListener('close', onClose);
        watchSwipe(sheet);
    }

    sheet.showModal();

    if (entryFor === null) {
        history.pushState({ ...(history.state ?? {}), goallineSheet: true }, '', window.location.href);
        entryFor = sheet;
    }
}

// A tap on the dimmed backdrop (the dialog itself, outside its content) closes any sheet.
document.addEventListener('click', (event) => {
    if (event.target instanceof HTMLDialogElement && event.target.classList.contains('gl-sheet') && event.target.open) {
        event.target.close();
    }
});

// Leaving the page with a sheet open (a link inside it): forget the entry; it stays in history as the page itself.
document.addEventListener('turbo:before-visit', () => {
    entryFor = null;
});
