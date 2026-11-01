/**
 * Recover an interrupted writing session in this tab without repeating publication.
 * @module mod_spatialforum/drafts
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Tiny from 'editor_tiny/editor';

export const init = options => {
    const key = 'mod_spatialforum:draft:' + options.scope;
    const read = () => {
        try {
            return JSON.parse(window.sessionStorage.getItem(key) || 'null');
        } catch (error) {
            return null;
        }
    };
    const remove = () => {
        try {
            window.sessionStorage.removeItem(key);
        } catch (error) {
            // The status below reports when recovery storage is unavailable.
        }
    };
    if (options.clear) {
        if (read()?.submissionkey === options.clear) {
            remove();
        }
        return;
    }
    const textarea = document.querySelector('textarea[name="message_editor[text]"]');
    const form = textarea?.closest('form');
    if (!form || form.dataset.draftInitialised) {
        return;
    }
    form.dataset.draftInitialised = '1';
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    status.className = 'alert alert-info';
    form.prepend(status);
    const fields = ['subject', 'branchname', 'showparent', 'followthread', 'submissionkey', 'revision',
        'message_editor[itemid]', 'message_editor[format]'];
    const field = name => form.querySelector('[name="' + name + '"]');
    const editor = () => Tiny.getInstanceForElement(textarea);
    let pending = read();
    let stopped = false;
    if (!pending || typeof pending.body !== 'string' || !Number.isFinite(pending.saved)
            || Date.now() - pending.saved > 86400000 || !options.restore) {
        pending = null;
        remove();
    }
    const snapshot = () => {
        const body = editor()?.getContent() ?? textarea.value;
        const data = {body};
        fields.forEach(name => {
            const node = field(name);
            if (node) {
                data[name] = node.type === 'checkbox' ? node.checked : node.value;
            }
        });
        return data;
    };
    let previous = JSON.stringify(snapshot());
    const capture = () => {
        if (stopped || pending) {
            return;
        }
        const data = snapshot();
        const signature = JSON.stringify(data);
        if (signature === previous) {
            return;
        }
        try {
            window.sessionStorage.setItem(key, JSON.stringify({...data, saved: Date.now()}));
            previous = signature;
            status.textContent = options.saved;
        } catch (error) {
            status.textContent = options.unavailable;
        }
    };
    if (pending) {
        status.textContent = options.available + ' ';
        const restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'btn btn-primary';
        restore.textContent = options.restoreLabel;
        restore.addEventListener('click', () => {
            // Keep the original publication token: an uncertain earlier submission may already exist.
            fields.forEach(name => {
                const node = field(name);
                if (node && Object.hasOwn(pending, name)) {
                    if (node.type === 'checkbox') {
                        node.checked = !!pending[name];
                    } else {
                        node.value = pending[name];
                    }
                }
            });
            textarea.value = pending.body;
            editor()?.setContent(pending.body);
            pending = null;
            previous = '';
            capture();
            editor()?.focus();
        });
        const discard = document.createElement('button');
        discard.type = 'button';
        discard.className = 'btn btn-link';
        discard.textContent = options.discardLabel;
        discard.addEventListener('click', () => {
            pending = null;
            remove();
            status.textContent = options.ready;
        });
        status.append(restore, discard);
    } else {
        status.textContent = options.ready;
    }
    form.addEventListener('input', capture);
    form.addEventListener('change', capture);
    form.addEventListener('click', event => {
        if (event.target.closest('[name="cancel"]')) {
            stopped = true;
            remove();
        }
    });
    form.addEventListener('submit', event => {
        if (event.submitter?.name === 'cancel') {
            stopped = true;
            remove();
        } else {
            capture();
        }
    });
    // Tiny's editable body lives in an iframe; capture its current public editor value too.
    const interval = window.setInterval(() => {
        if (!form.isConnected) {
            window.clearInterval(interval);
            return;
        }
        capture();
    }, 1000);
    window.addEventListener('pagehide', capture);
    if (!options.restore) {
        previous = '';
        capture();
    }
};
