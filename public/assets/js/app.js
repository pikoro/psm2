/**
 * PHPSwapMeet 2 - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // Flash auto-dismiss
    document.querySelectorAll('[data-dismiss="flash"]').forEach(function (el) {
        var closeBtn = el.querySelector('.flash-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                el.remove();
            });
        }
        setTimeout(function () {
            if (el.parentNode) {
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.3s';
                setTimeout(function () {
                    if (el.parentNode) el.remove();
                }, 300);
            }
        }, 5000);
    });

    // Gallery lightbox
    initGallery();

    // Character counters
    initCharCounters();

    // File preview
    initFilePreview();

    // Confirm dialogs for delete links
    initConfirmLinks();
});

/**
 * Gallery lightbox
 */
function initGallery() {
    var thumbs = document.querySelectorAll('.gallery-thumb');
    var mainImg = document.getElementById('gallery-main-img');
    if (!thumbs.length || !mainImg) return;

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            var src = this.getAttribute('data-src') || this.src;
            mainImg.src = src;
            thumbs.forEach(function (t) { t.classList.remove('active'); });
            this.classList.add('active');
        });
    });
}

/**
 * Character counters
 */
function initCharCounters() {
    document.querySelectorAll('.char-counter').forEach(function (counter) {
        var target = document.querySelector(counter.getAttribute('data-target'));
        if (!target) return;

        var maxLen = parseInt(target.getAttribute('maxlength'), 10) || 5000;
        updateCounter(counter, target, maxLen);

        target.addEventListener('input', function () {
            updateCounter(counter, target, maxLen);
        });
    });
}

function updateCounter(counter, target, maxLen) {
    var remaining = maxLen - target.value.length;
    counter.textContent = remaining.toString();
    if (remaining < 50) {
        counter.style.color = '#dc2626';
    } else if (remaining < 100) {
        counter.style.color = '#d97706';
    } else {
        counter.style.color = '';
    }
}

/**
 * File preview
 */
function initFilePreview() {
    var fileInput = document.getElementById('photos');
    if (!fileInput) return;

    fileInput.addEventListener('change', function () {
        var previewContainer = document.getElementById('photo-previews');
        if (!previewContainer) {
            previewContainer = document.createElement('div');
            previewContainer.id = 'photo-previews';
            previewContainer.className = 'file-previews';
            fileInput.parentNode.appendChild(previewContainer);
        }
        previewContainer.innerHTML = '';

        Array.from(this.files).forEach(function (file) {
            if (!file.type.startsWith('image/')) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '80px';
                img.style.maxHeight = '80px';
                img.style.margin = '4px';
                img.style.borderRadius = '4px';
                img.style.border = '1px solid #e5e7eb';
                previewContainer.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });
}

/**
 * Confirm links (onclick)
 */
function initConfirmLinks() {
    document.querySelectorAll('[data-confirm]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Sync CSRF token for AJAX requests
 */
function getCSRFToken() {
    var input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

function setCSRFHeader(xhr) {
    var token = getCSRFToken();
    if (token) {
        xhr.setRequestHeader('X-CSRF-TOKEN', token);
    }
}
