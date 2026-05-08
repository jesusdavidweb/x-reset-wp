(function () {
    'use strict';

    var optionLabels = xResetWP.optionLabels;

    var $form             = document.getElementById('xrp-form');
    var $modal            = document.getElementById('xrp-modal');
    var $submit           = document.getElementById('xrp-submit');
    var $selectAll        = document.getElementById('xrp-select-all');
    var $modalConfirm     = document.getElementById('xrp-modal-confirm');
    var $modalProgress    = document.getElementById('xrp-modal-progress');
    var $modalList        = document.getElementById('xrp-modal-list');
    var $logList          = document.getElementById('xrp-log-list');
    var $progressTitle    = document.getElementById('xrp-progress-title');
    var $progressStep     = document.getElementById('xrp-progress-step');
    var $progressFill     = document.querySelector('.xrp-progress-fill');
    var $doneButtons      = document.getElementById('xrp-modal-done-buttons');
    var $dryRun           = document.getElementById('xrp-dry-run');
    var $dryRunNotice     = document.getElementById('xrp-dry-run-notice');
    var $dateFrom         = document.getElementById('xrp-date-from');
    var $dateTo           = document.getElementById('xrp-date-to');
    var $batchRange       = document.getElementById('xrp-batch');
    var $batchValue       = document.getElementById('xrp-batch-value');
    var $factoryConfirm   = document.getElementById('xrp-factory-confirm');
    var $factoryInput     = document.getElementById('xrp-factory-input');
    var $exportAudit      = document.getElementById('xrp-export-audit');

    var isRunning = false;
    var $checkboxes = null;
    var allOptions = Object.keys(optionLabels);

    // ─── Init: load stats ──────────────────────────────────────────────

    function loadStats() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', xResetWP.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            if (xhr.status !== 200) return;
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    renderStats(res.data);
                }
            } catch (_) {}
        };
        xhr.send('action=x_reset_wp_stats&nonce=' + encodeURIComponent(xResetWP.nonce));
    }

    function renderStats(stats) {
        for (var key in stats) {
            var el = document.getElementById('xrp-count-' + key);
            if (!el) continue;
            var count = stats[key];
            if (count === -1) {
                el.textContent = xResetWP.strings.requiresWc;
                el.className = 'xrp-option-count xrp-count-wc';
            } else if (count === 0) {
                el.textContent = xResetWP.strings.noItems;
                el.className = 'xrp-option-count xrp-count-empty';
            } else {
                el.textContent = count + ' ' + xResetWP.strings.items;
                el.className = 'xrp-option-count';
            }
        }
    }

    // ─── Checkbox helpers ──────────────────────────────────────────────

    function getCheckboxes() {
        if (!$checkboxes) {
            $checkboxes = $form.querySelectorAll('input[type="checkbox"][name="options[]"]');
        }
        return $checkboxes;
    }

    function getSelected() {
        var selected = [];
        getCheckboxes().forEach(function (cb) {
            if (cb.checked) selected.push(cb.value);
        });
        return selected;
    }

    function updateSubmit() {
        $submit.disabled = getSelected().length === 0;
    }

    function setAll(checked) {
        getCheckboxes().forEach(function (cb) { cb.checked = checked; });
        updateSelectAllLabel();
        updateSubmit();
        updateFactoryConfirm();
    }

    function updateSelectAllLabel() {
        var allChecked = getSelected().length === allOptions.length;
        $selectAll.textContent = allChecked ? xResetWP.strings.deselectAll : xResetWP.strings.selectAll;
    }

    // ─── Category collapse ─────────────────────────────────────────────

    function initCategories() {
        document.querySelectorAll('.xrp-category-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var expanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', !expanded);
                var body = btn.closest('.xrp-category').querySelector('.xrp-category-body');
                if (body) {
                    body.style.display = expanded ? 'none' : '';
                }
            });
        });

        document.querySelectorAll('.xrp-category-select').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cat = btn.closest('.xrp-category');
                var checkboxes = cat.querySelectorAll('input[type="checkbox"][name="options[]"]');
                var allChecked = true;
                checkboxes.forEach(function (cb) { if (!cb.checked) allChecked = false; });
                checkboxes.forEach(function (cb) { cb.checked = !allChecked; });
                updateSelectAllLabel();
                updateSubmit();
                updateFactoryConfirm();
            });
        });
    }

    // ─── Batch size ────────────────────────────────────────────────────

    $batchRange.addEventListener('input', function () {
        $batchValue.textContent = this.value;
    });

    // ─── Dry run ───────────────────────────────────────────────────────

    $dryRun.addEventListener('change', function () {
        $dryRunNotice.style.display = this.checked ? 'flex' : 'none';
    });

    // ─── Factory reset confirmation ────────────────────────────────────

    function updateFactoryConfirm() {
        var selected = getSelected();
        var hasFactory = selected.indexOf('factory_reset') !== -1;
        $factoryConfirm.style.display = hasFactory ? 'block' : 'none';
        if (!hasFactory) {
            $factoryInput.value = '';
        }
    }

    // ─── Events: checkbox change ──────────────────────────────────────

    $form.addEventListener('change', function (e) {
        if (e.target.type === 'checkbox' && e.target.name === 'options[]') {
            updateSelectAllLabel();
            updateSubmit();
            updateFactoryConfirm();
        }
    });

    // ─── Event: select all ──────────────────────────────────────────

    $selectAll.addEventListener('click', function () {
        var allChecked = getSelected().length === allOptions.length;
        setAll(!allChecked);
    });

    // ─── Event: submit form -> open modal ────────────────────────────

    $form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (isRunning) return;

        var selected = getSelected();
        if (selected.length === 0) return;

        $modalList.innerHTML = '';
        selected.forEach(function (opt) {
            if (opt === 'factory_reset') return;
            var li = document.createElement('li');
            li.textContent = optionLabels[opt] || opt;
            $modalList.appendChild(li);
        });

        if (selected.indexOf('factory_reset') !== -1) {
            var li = document.createElement('li');
            li.textContent = optionLabels.factory_reset + ' (' + xResetWP.strings.cleanupDone.toLowerCase() + ')';
            li.style.fontWeight = '600';
            li.style.color = 'var(--xrp-danger)';
            $modalList.appendChild(li);
        }

        $factoryInput.value = '';
        $factoryConfirm.style.display = 'none';

        $modalConfirm.style.display = 'block';
        $modalProgress.style.display = 'none';
        $doneButtons.style.display = 'none';
        $modal.style.display = 'flex';
    });

    // ─── Modal: cancel ──────────────────────────────────────────────

    document.getElementById('xrp-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('xrp-modal-close').addEventListener('click', closeModal);

    $modal.addEventListener('click', function (e) {
        if (e.target === $modal && !isRunning) closeModal();
    });

    function closeModal() {
        if (isRunning) return;
        clearProgress();
        $modal.style.display = 'none';
    }

    // ─── Modal: start ───────────────────────────────────────────────

    document.getElementById('xrp-modal-start').addEventListener('click', function () {
        if (isRunning) return;

        var selected = getSelected();

        if (selected.indexOf('factory_reset') !== -1 && $factoryInput.value !== 'DELETE') {
            alert(xResetWP.strings.factoryResetInvalid);
            return;
        }

        var queue = [];
        selected.forEach(function (opt) {
            if (opt === 'factory_reset') {
                allOptions.forEach(function (o) {
                    if (o !== 'factory_reset' && queue.indexOf(o) === -1) {
                        queue.push(o);
                    }
                });
            } else {
                queue.push(opt);
            }
        });

        window._xrpQueue = queue;
        window._xrpQueueIndex = 0;

        saveProgress(queue, 0);

        $modalConfirm.style.display = 'none';
        $modalProgress.style.display = 'block';
        $doneButtons.style.display = 'none';
        $logList.innerHTML = '';
        $progressFill.style.width = '0%';

        isRunning = true;
        processNext();
    });

    // ─── Process loop ───────────────────────────────────────────────

    function processNext() {
        var queue = window._xrpQueue || [];
        var index = window._xrpQueueIndex || 0;

        if (index >= queue.length) {
            finishAll();
            return;
        }

        var optionKey = queue[index];
        var totalOptions = queue.length;

        $progressTitle.textContent = xResetWP.strings.cleaning + ' ' + (optionLabels[optionKey] || optionKey);
        $progressFill.style.width = ((index / totalOptions) * 100) + '%';

        processOption(optionKey, function () {
            window._xrpQueueIndex = index + 1;
            saveProgress(queue, index + 1);
            processNext();
        });
    }

    function processOption(optionKey, onDone) {
        var data = new FormData();
        data.append('action',  'x_reset_wp_process');
        data.append('nonce',   xResetWP.nonce);
        data.append('option',  optionKey);
        data.append('batch',   $batchRange ? $batchRange.value : 100);
        data.append('dry_run', $dryRun && $dryRun.checked ? '1' : '0');
        if ($dateFrom && $dateFrom.value) data.append('date_from', $dateFrom.value);
        if ($dateTo && $dateTo.value)     data.append('date_to',   $dateTo.value);

        $progressStep.textContent = xResetWP.strings.processing;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', xResetWP.ajaxUrl, true);

        xhr.onload = function () {
            if (xhr.status !== 200) {
                addLogEntry(xResetWP.strings.connectionErr + ' (' + (optionLabels[optionKey] || optionKey) + ')', true);
                onDone();
                return;
            }

            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (err) {
                addLogEntry(xResetWP.strings.invalidResp + ' (' + (optionLabels[optionKey] || optionKey) + ')', true);
                onDone();
                return;
            }

            if (!response.success) {
                addLogEntry(response.data && response.data.message || xResetWP.strings.unknownErr, true);
                onDone();
                return;
            }

            var result = response.data;

            if (!result.done) {
                $progressStep.textContent = result.message || xResetWP.strings.processing;
                processOption(optionKey, onDone);
            } else {
                if (result.dry_run) {
                    var li = document.createElement('li');
                    li.textContent = '[DRY-RUN] ' + (result.message || '');
                    li.style.color = 'var(--xrp-accent)';
                    li.style.fontStyle = 'italic';
                    $logList.appendChild(li);
                } else {
                    addLogEntry(result.message, false);
                }
                onDone();
            }
        };

        xhr.onerror = function () {
            addLogEntry(xResetWP.strings.networkErr + ' (' + (optionLabels[optionKey] || optionKey) + ')', true);
            onDone();
        };

        xhr.send(data);
    }

    function finishAll() {
        isRunning = false;
        clearProgress();
        $progressFill.style.width = '100%';
        $progressTitle.textContent = xResetWP.strings.cleanupDone;
        $progressStep.textContent = xResetWP.strings.allFinished;
        $doneButtons.style.display = 'flex';

        getCheckboxes().forEach(function (cb) { cb.checked = false; });
        $selectAll.textContent = xResetWP.strings.selectAll;
        updateSubmit();
        updateFactoryConfirm();

        loadStats();
    }

    function addLogEntry(message, isError) {
        var li = document.createElement('li');
        li.textContent = message;
        if (isError) {
            li.classList.add('xrp-log-error');
        }
        $logList.appendChild(li);
        $logList.scrollTop = $logList.scrollHeight;
    }

    // ─── Progress persistence (sessionStorage) ─────────────────────

    function saveProgress(queue, index) {
        try {
            sessionStorage.setItem('xrp_progress', JSON.stringify({
                queue: queue,
                index: index,
                timestamp: Date.now()
            }));
        } catch (_) {}
    }

    function clearProgress() {
        try { sessionStorage.removeItem('xrp_progress'); } catch (_) {}
    }

    function resumeSavedProgress() {
        try {
            var saved = sessionStorage.getItem('xrp_progress');
            if (!saved) return;
            var progress = JSON.parse(saved);
            if (!progress.queue || progress.queue.length === 0 || progress.index >= progress.queue.length) {
                clearProgress();
                return;
            }
            if (!confirm(xResetWP.strings.resumePrompt)) {
                clearProgress();
                return;
            }
            window._xrpQueue = progress.queue;
            window._xrpQueueIndex = progress.index;

            $modalConfirm.style.display = 'none';
            $modalProgress.style.display = 'block';
            $doneButtons.style.display = 'none';
            $logList.innerHTML = '';
            $progressFill.style.width = ((progress.index / progress.queue.length) * 100) + '%';

            isRunning = true;
            processNext();
        } catch (_) {
            clearProgress();
        }
    }

    // ─── Audit log export ──────────────────────────────────────────

    if ($exportAudit) {
        $exportAudit.addEventListener('click', function () {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', xResetWP.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status !== 200) return;
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
                        alert(xResetWP.strings.auditEmpty);
                        return;
                    }
                    var headers = Object.keys(res.data[0]);
                    var csv = headers.join(',') + '\n';
                    res.data.forEach(function (row) {
                        csv += headers.map(function (h) {
                            var val = (row[h] || '').toString();
                            if (val.includes(',') || val.includes('"') || val.includes('\n')) {
                                val = '"' + val.replace(/"/g, '""') + '"';
                            }
                            return val;
                        }).join(',') + '\n';
                    });

                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'x-reset-wp-audit-log.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } catch (_) {}
            };
            xhr.send('action=x_reset_wp_get_audit&nonce=' + encodeURIComponent(xResetWP.nonce));
        });
    }

    // ─── Keyboard: close modal with Escape ──────────────────────────

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && $modal.style.display === 'flex' && !isRunning) {
            closeModal();
        }
    });

    // ─── Init ───────────────────────────────────────────────────────

    initCategories();
    loadStats();
    updateFactoryConfirm();

    getCheckboxes().forEach(function (cb) {
        cb.addEventListener('change', updateFactoryConfirm);
    });

    resumeSavedProgress();

})();
