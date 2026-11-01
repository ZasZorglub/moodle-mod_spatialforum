/**
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file is part of Moodle - http://moodle.org/

/** Selection-based replies and a bounded, editable list of quote/comment blocks. */
export const init = () => {
    const composer = document.querySelector('[data-excerpt-composer]');
    const normalise = text => text.replace(/\s+/gu, ' ').trim();
    const selected = message => {
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount || selection.isCollapsed) {
            return '';
        }
        const range = selection.getRangeAt(0);
        if (!message.contains(range.startContainer) || !message.contains(range.endContainer)) {
            return '';
        }
        // Never attribute an embedded quotation or source label to this post's author.
        const exclusions = message.querySelectorAll('blockquote, .spatialforum-excerpt-source');
        if (Array.from(exclusions).some(node => range.intersectsNode(node))) {
            return '';
        }
        const text = normalise(selection.toString());
        return text.length <= 4000 ? text : '';
    };
    if (composer && !composer.dataset.initialised) {
        composer.dataset.initialised = '1';
        const form = composer.closest('form');
        const input = form.querySelector('[name="excerptdraft"]');
        const container = composer.querySelector('[data-excerpt-parts]');
        const status = composer.querySelector('[data-excerpt-status]');
        const add = composer.querySelector('[data-add-excerpt]');
        const origin = document.querySelector('.spatialforum-reply-context .spatialforum-message');
        let parts = [];
        try {
            parts = JSON.parse(input.value || '[]');
        } catch (error) {
            parts = [];
        }
        let selection = '';
        const save = () => { input.value = JSON.stringify(parts); };
        const render = () => {
            container.replaceChildren();
            parts.forEach((part, index) => {
                const block = document.createElement('div');
                block.className = 'spatialforum-excerpt-draft';
                const quote = document.createElement('blockquote');
                quote.textContent = part.quote;
                const label = document.createElement('label');
                label.textContent = composer.dataset.commentLabel;
                const comment = document.createElement('textarea');
                comment.className = 'form-control';
                comment.rows = 4;
                comment.maxLength = 4000;
                comment.value = part.comment;
                label.appendChild(comment);
                comment.addEventListener('input', () => {part.comment = comment.value; save();});
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-link';
                remove.textContent = composer.dataset.removeLabel;
                remove.addEventListener('click', () => {
                    parts.splice(index, 1);
                    render();
                    save();
                    const next = container.querySelectorAll('textarea');
                    if (next.length) {
                        next[Math.min(index, next.length - 1)].focus();
                    } else {
                        composer.querySelector('[data-excerpt-heading]').focus();
                    }
                    status.textContent = composer.dataset.removedLabel;
                });
                block.append(quote, label, remove);
                container.appendChild(block);
            });
            save();
            const messageLabel = form.querySelector('label[for="id_message_editor"]');
            if (messageLabel) {
                messageLabel.textContent = parts.length ? composer.dataset.closingLabel : composer.dataset.messageLabel;
            }
        };
        render();
        document.addEventListener('selectionchange', () => {
            selection = origin ? selected(origin) : '';
            add.disabled = !selection;
            add.setAttribute('aria-disabled', String(add.disabled));
        });
        // Preserve the selection when pointer focus moves to the action button.
        add.addEventListener('mousedown', event => event.preventDefault());
        add.addEventListener('click', () => {
            if (!selection) {
                return;
            }
            if (parts.length >= 10) {
                status.textContent = composer.dataset.limitLabel;
                return;
            }
            parts.push({quote: selection, comment: ''});
            render();
            container.lastElementChild.querySelector('textarea').focus();
        });
        form.addEventListener('submit', save);
    }
    document.querySelectorAll('[data-excerpt-start]').forEach(form => {
        if (form.dataset.initialised) {
            return;
        }
        form.dataset.initialised = '1';
        const message = form.closest('[data-post-id]').querySelector('.spatialforum-post-detail > .spatialforum-message');
        const button = form.querySelector('button');
        const input = form.querySelector('[name="initialexcerpt"]');
        document.addEventListener('selectionchange', () => {
            input.value = selected(message);
            button.disabled = !input.value;
            button.setAttribute('aria-disabled', String(button.disabled));
            form.classList.toggle('has-selection', !!input.value);
            form.querySelector('.spatialforum-selection-help').textContent = input.value ? form.dataset.ready : form.dataset.hint;
        });
        button.addEventListener('mousedown', event => event.preventDefault());
        form.addEventListener('submit', event => {
            if (!input.value) {
                event.preventDefault();
            }
        });
    });
};
