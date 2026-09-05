// Comments/replies + confirmation prompts for claim/post actions.
(function () {
    const list = document.getElementById('comment-list');
    const countEl = document.getElementById('comments-count');
    const mainForm = document.getElementById('comment-form');

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    function csrfToken() {
        return mainForm?.querySelector('input[name="csrf_token"]')?.value || '';
    }

    function autoGrow(textarea) {
        if (!textarea) return;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    function relativeTime(timestamp) {
        const then = new Date(timestamp).getTime();
        if (!Number.isFinite(then)) return '';
        const seconds = Math.max(0, Math.floor((Date.now() - then) / 1000));
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        if (seconds < 2592000) return `${Math.floor(seconds / 86400)}d ago`;
        return new Date(timestamp).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function updateRelativeTimes() {
        document.querySelectorAll('.relative-time[data-timestamp]').forEach((el) => {
            const label = relativeTime(el.dataset.timestamp);
            if (label) el.textContent = label;
        });
    }

    function avatarContent(photoUrl, fallback) {
        const photo = String(photoUrl || '').trim();
        if (photo) return `<img src="${escapeHtml(photo)}" alt="">`;
        return escapeHtml(fallback || 'U');
    }

    function replyFormMarkup(parentId, photoUrl = '') {
        const token = escapeHtml(csrfToken());
        return `<form method="post" action="" class="inline-reply-form" data-parent-id="${parentId}" hidden>
            <input type="hidden" name="csrf_token" value="${token}">
            <input type="hidden" name="action" value="comment">
            <input type="hidden" name="parent_id" value="${parentId}">
            <div class="comment-composer comment-composer-reply">
                <span class="comment-avatar comment-avatar-sm comment-avatar-self" aria-hidden="true">${avatarContent(photoUrl, 'You')}</span>
                <textarea name="message" rows="1" maxlength="1000" placeholder="Write a reply..." required></textarea>
                <button type="submit" class="comment-send-btn" aria-label="Post reply" title="Post reply">&#10148;</button>
            </div>
            <button type="button" class="reply-cancel-btn">Cancel</button>
        </form>`;
    }

    function commentMarkup(data, isReply) {
        const initial = escapeHtml((data.author_name || 'U').trim().charAt(0).toUpperCase());
        const name = escapeHtml(data.author_name || 'User');
        const message = escapeHtml(data.message || '').replaceAll('\n', '<br>');
        const profileUrl = escapeHtml(data.profile_url || '#');
        const timestamp = escapeHtml(data.created_at || new Date().toISOString());
        const parentId = Number(data.parent_id || data.id);
        const avatar = avatarContent(data.author_photo_url, initial);

        if (isReply) {
            return `<article class="comment-reply" id="comment-${data.id}">
                <a class="comment-avatar comment-avatar-sm" href="${profileUrl}" aria-label="View ${name}'s profile">${avatar}</a>
                <div class="comment-content-wrap">
                    <div class="comment-bubble"><a class="comment-author" href="${profileUrl}">${name}</a><p>${message}</p></div>
                    <div class="comment-meta-actions">
                        <span class="relative-time" data-timestamp="${timestamp}">just now</span>
                        <button type="button" class="comment-reply-btn" data-reply-to="${parentId}" data-reply-name="${name}">Reply</button>
                    </div>
                </div>
            </article>`;
        }

        return `<article class="comment-thread" id="comment-${data.id}" data-comment-id="${data.id}">
            <div class="comment-row">
                <a class="comment-avatar" href="${profileUrl}" aria-label="View ${name}'s profile">${avatar}</a>
                <div class="comment-content-wrap">
                    <div class="comment-bubble"><a class="comment-author" href="${profileUrl}">${name}</a><p>${message}</p></div>
                    <div class="comment-meta-actions">
                        <span class="relative-time" data-timestamp="${timestamp}">just now</span>
                        <button type="button" class="comment-reply-btn" data-reply-to="${data.id}" data-reply-name="${name}">Reply</button>
                    </div>
                    <div class="comment-replies" data-replies-for="${data.id}"></div>
                    ${replyFormMarkup(data.id, data.author_photo_url || '')}
                </div>
            </div>
        </article>`;
    }

    async function submitComment(form) {
        if (form.dataset.submitting === '1') return;
        const textarea = form.querySelector('textarea[name="message"]');
        const message = textarea?.value.trim();
        if (!message) return;

        const submit = form.querySelector('[type="submit"]');
        form.dataset.submitting = '1';
        if (submit) submit.disabled = true;

        const body = new FormData(form);
        body.set('response_format', 'json');

        try {
            const response = await fetch(window.location.href.split('#')[0], {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body,
                credentials: 'same-origin'
            });

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('The server returned an unexpected response. Please refresh and try again.');
            }
            const data = await response.json();
            if (!response.ok || data.error) throw new Error(data.error || 'Could not post comment.');

            if (data.parent_id) {
                const replies = document.querySelector(`[data-replies-for="${data.parent_id}"]`);
                if (!replies) throw new Error('Reply thread could not be found.');
                replies.insertAdjacentHTML('beforeend', commentMarkup(data, true));
                form.hidden = true;
            } else {
                if (!list) throw new Error('Comment list could not be found.');
                list.insertAdjacentHTML('beforeend', commentMarkup(data, false));
            }

            textarea.value = '';
            textarea.style.height = '';
            document.getElementById('comments-empty')?.remove();
            if (countEl) countEl.textContent = String(Number(countEl.textContent || 0) + 1);
            updateRelativeTimes();
        } catch (error) {
            alert(error.message || 'Could not post comment. Please try again.');
        } finally {
            delete form.dataset.submitting;
            if (submit) submit.disabled = false;
        }
    }

    // Delegation means comments/reply forms inserted after an AJAX post work immediately.
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('#comment-form, .inline-reply-form');
        if (!form) return;
        event.preventDefault();
        submitComment(form);
    });

    document.addEventListener('input', (event) => {
        if (event.target.matches('.comment-composer textarea')) autoGrow(event.target);
    });

    document.addEventListener('keydown', (event) => {
        const textarea = event.target.closest('.comment-composer textarea');
        if (!textarea) return;
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            textarea.closest('form')?.requestSubmit();
        }
    });

    document.addEventListener('click', (event) => {
        const replyButton = event.target.closest('.comment-reply-btn');
        if (replyButton) {
            const parentId = replyButton.dataset.replyTo;
            const thread = document.querySelector(`.comment-thread[data-comment-id="${parentId}"]`);
            const form = thread?.querySelector('.inline-reply-form');
            if (!form) return;
            document.querySelectorAll('.inline-reply-form').forEach((other) => {
                if (other !== form) other.hidden = true;
            });
            form.hidden = false;
            const textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.placeholder = `Reply to ${replyButton.dataset.replyName || 'comment'}...`;
                textarea.focus();
                autoGrow(textarea);
            }
            return;
        }

        const cancel = event.target.closest('.reply-cancel-btn');
        if (cancel) {
            const form = cancel.closest('.inline-reply-form');
            if (form) {
                form.hidden = true;
                const textarea = form.querySelector('textarea');
                if (textarea) {
                    textarea.value = '';
                    textarea.style.height = '';
                }
            }
        }
    });

    document.querySelectorAll('form[action*="respond_claim.php"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const decision = event.submitter && event.submitter.value;
            if (decision === 'rejected' && !confirm('Reject this claim request?')) event.preventDefault();
        });
    });

    updateRelativeTimes();
    window.setInterval(updateRelativeTimes, 30000);
})();
