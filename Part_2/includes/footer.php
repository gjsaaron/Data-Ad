</div>

<div id="toast">
    <div class="toast-inner">
        <span class="toast-icon" id="toast-icon">⚠️</span>
        <div class="toast-body">
            <div class="toast-title" id="toast-title">Error</div>
            <div class="toast-msg"  id="toast-msg"></div>
        </div>
        <button class="toast-close" onclick="hideToast()">✕</button>
    </div>
</div>

<script>
let _toastTimer;

function showToast(message, type = 'error', title = null) {
    const t    = document.getElementById('toast');
    const icon = document.getElementById('toast-icon');
    const tit  = document.getElementById('toast-title');
    const msg  = document.getElementById('toast-msg');

    const cfg = {
        error:   { cls: 'toast-error',   icon: '❌', label: 'Error'   },
        success: { cls: 'toast-success', icon: '✅', label: 'Success' },
        warning: { cls: 'toast-warning', icon: '⚠️', label: 'Warning' },
    }[type] || { cls: 'toast-error', icon: '❌', label: 'Error' };

    t.className = 'show ' + cfg.cls;
    icon.textContent = cfg.icon;
    tit.textContent  = title || cfg.label;
    msg.textContent  = message;

    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(hideToast, 5000);
}

function hideToast() {
    document.getElementById('toast').classList.remove('show');
}

document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('input[data-numeric]').forEach(function (input) {
        var lbl = input.dataset.label || input.name;

        function reject(msg) {
            input.classList.add('input-error');
            showToast(msg || '"' + lbl + '" only accepts whole numbers.', 'warning', 'Numbers Only');
            setTimeout(function () { input.classList.remove('input-error'); }, 1500);
        }

        input.addEventListener('beforeinput', function (e) {
            if (!e.data) return;
            if (!/^\d+$/.test(e.data)) {
                e.preventDefault();
                reject();
            }
        });

        input.addEventListener('paste', function (e) {
            e.preventDefault();
            var text   = (e.clipboardData || window.clipboardData).getData('text');
            var digits = text.replace(/[^0-9]/g, '');
            if (text !== digits) reject('"' + lbl + '" only accepts digits — non-numeric characters were removed.');
            var start = this.selectionStart, end = this.selectionEnd;
            this.value = this.value.slice(0, start) + digits + this.value.slice(end);
        });

        input.addEventListener('drop', function (e) {
            var text = e.dataTransfer.getData('text');
            if (!/^\d+$/.test(text)) {
                e.preventDefault();
                reject();
            }
        });

        input.addEventListener('input', function () {
            var clean = this.value.replace(/[^0-9]/g, '');
            if (clean !== this.value) {
                this.value = clean;
                reject();
            }
        });
    });

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var firstError = null;

            form.querySelectorAll('input[required], select[required]').forEach(function (field) {
                if (!field.value.trim()) {
                    field.classList.add('input-error');
                    firstError = firstError || ('"' + (field.dataset.label || field.placeholder || field.name) + '" is required.');
                    setTimeout(function () { field.classList.remove('input-error'); }, 2000);
                }
            });

            form.querySelectorAll('input[data-numeric]').forEach(function (field) {
                if (field.value === '') return;
                if (!/^\d+$/.test(field.value)) {
                    field.classList.add('input-error');
                    firstError = firstError || ('"' + (field.dataset.label || field.name) + '" must be a whole number (digits only).');
                    setTimeout(function () { field.classList.remove('input-error'); }, 2000);
                    return;
                }
                var v   = parseInt(field.value, 10);
                var min = field.dataset.min !== undefined ? parseInt(field.dataset.min, 10) : null;
                var max = field.dataset.max !== undefined ? parseInt(field.dataset.max, 10) : null;
                var lbl = field.dataset.label || field.name;
                if (min !== null && v < min) {
                    field.classList.add('input-error');
                    firstError = firstError || ('"' + lbl + '" must be at least ' + min + '.');
                    setTimeout(function () { field.classList.remove('input-error'); }, 2000);
                } else if (max !== null && v > max) {
                    field.classList.add('input-error');
                    firstError = firstError || ('"' + lbl + '" must be at most ' + max + '.');
                    setTimeout(function () { field.classList.remove('input-error'); }, 2000);
                }
            });

            if (firstError) {
                e.preventDefault();
                showToast(firstError, 'error', 'Validation Error');
            }
        });
    });
});
</script>

</body>
</html>
