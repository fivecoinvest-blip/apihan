#!/usr/bin/env php
<?php
// PHP script to update showToast() function to Bootstrap toasts in admin_lte.php

$file = 'admin_lte.php';
$content = file_get_contents($file);

// Replace the showToast function with Bootstrap toast version
$oldToast = <<<'OLD'
        function showToast(message, type='success'){
            const cont = document.getElementById('toast-container');
            if (!cont) return;
            const el = document.createElement('div');
            el.className = 'toast ' + (type==='error' ? 'error' : 'success');
            el.textContent = message;
            cont.appendChild(el);
            requestAnimationFrame(()=> el.classList.add('show'));
            setTimeout(()=>{
                el.classList.remove('show');
                setTimeout(()=> el.remove(), 200);
            }, 3000);
        }
OLD;

$newToast = <<<'NEW'
        function showToast(message, type='success'){
            const cont = document.getElementById('toast-container');
            if (!cont) return;
            const toastId = 'toast-' + Date.now();
            const bgClass = type === 'error' ? 'bg-danger' : 'bg-success';
            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">${escapeHtml(message)}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            cont.insertAdjacentHTML('beforeend', toastHTML);
            const toastEl = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        }
NEW;

$content = str_replace($oldToast, $newToast, $content);

// Update toast container
$content = str_replace(
    '<div class="toast-container" id="toast-container"></div>',
    '<div class="toast-container position-fixed top-0 end-0 p-3" id="toast-container"></div>',
    $content
);

file_put_contents($file, $content);
echo "✓ Updated showToast() to Bootstrap toast component\n";
echo "✓ Updated toast container with Bootstrap classes\n";
