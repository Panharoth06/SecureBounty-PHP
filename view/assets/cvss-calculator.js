/**
 * CvssCalculator — Client-side CVSS 3.1 Base Score Calculator
 *
 * Self-contained module that renders metric toggle buttons, computes the
 * CVSS 3.1 base score, derives severity, and updates hidden form inputs.
 *
 * Usage: CvssCalculator.init('#cvss-calculator-container')
 *
 * @see Requirement 7.2 — Severity level via CVSS scoring
 * @see Requirement 8.4 — Associate reward policy based on severity
 */
const CvssCalculator = (function () {
    'use strict';

    // ─── CVSS 3.1 Metric Weight Constants ──────────────────────────────

    const AV_WEIGHTS = { N: 0.85, A: 0.62, L: 0.55, P: 0.20 };
    const AC_WEIGHTS = { L: 0.77, H: 0.44 };
    const PR_WEIGHTS_UNCHANGED = { N: 0.85, L: 0.62, H: 0.27 };
    const PR_WEIGHTS_CHANGED = { N: 0.85, L: 0.68, H: 0.50 };
    const UI_WEIGHTS = { N: 0.85, R: 0.62 };
    const C_WEIGHTS = { N: 0.00, L: 0.22, H: 0.56 };
    const I_WEIGHTS = { N: 0.00, L: 0.22, H: 0.56 };
    const A_WEIGHTS = { N: 0.00, L: 0.22, H: 0.56 };

    // Metric definitions: key, label, and value options
    const METRICS = [
        {
            key: 'AV',
            label: 'Attack Vector',
            options: [
                { value: 'N', label: 'Network' },
                { value: 'A', label: 'Adjacent' },
                { value: 'L', label: 'Local' },
                { value: 'P', label: 'Physical' }
            ]
        },
        {
            key: 'AC',
            label: 'Attack Complexity',
            options: [
                { value: 'L', label: 'Low' },
                { value: 'H', label: 'High' }
            ]
        },
        {
            key: 'PR',
            label: 'Privileges Required',
            options: [
                { value: 'N', label: 'None' },
                { value: 'L', label: 'Low' },
                { value: 'H', label: 'High' }
            ]
        },
        {
            key: 'UI',
            label: 'User Interaction',
            options: [
                { value: 'N', label: 'None' },
                { value: 'R', label: 'Required' }
            ]
        },
        {
            key: 'S',
            label: 'Scope',
            options: [
                { value: 'U', label: 'Unchanged' },
                { value: 'C', label: 'Changed' }
            ]
        },
        {
            key: 'C',
            label: 'Confidentiality',
            options: [
                { value: 'N', label: 'None' },
                { value: 'L', label: 'Low' },
                { value: 'H', label: 'High' }
            ]
        },
        {
            key: 'I',
            label: 'Integrity',
            options: [
                { value: 'N', label: 'None' },
                { value: 'L', label: 'Low' },
                { value: 'H', label: 'High' }
            ]
        },
        {
            key: 'A',
            label: 'Availability',
            options: [
                { value: 'N', label: 'None' },
                { value: 'L', label: 'Low' },
                { value: 'H', label: 'High' }
            ]
        }
    ];

    // Severity badge CSS classes (matches style.css badge classes)
    const SEVERITY_CLASSES = {
        none: 'badge-informational',
        low: 'badge-low',
        medium: 'badge-medium',
        high: 'badge-high',
        critical: 'badge-critical'
    };

    // ─── Core Calculation Functions ────────────────────────────────────

    /**
     * CVSS 3.1 Roundup function.
     * roundup(x) = ceil(x * 10) / 10.0
     */
    function roundUp(value) {
        return Math.ceil(value * 10) / 10.0;
    }

    /**
     * Compute CVSS 3.1 base score from metric selections.
     * Returns { score, severity, vector } or null if incomplete.
     */
    function computeScore(selections) {
        // Verify all metrics are selected
        for (var i = 0; i < METRICS.length; i++) {
            if (!selections[METRICS[i].key]) {
                return null;
            }
        }

        var scopeChanged = (selections.S === 'C');

        // Get weights
        var avWeight = AV_WEIGHTS[selections.AV];
        var acWeight = AC_WEIGHTS[selections.AC];
        var uiWeight = UI_WEIGHTS[selections.UI];
        var cWeight = C_WEIGHTS[selections.C];
        var iWeight = I_WEIGHTS[selections.I];
        var aWeight = A_WEIGHTS[selections.A];

        // Privileges Required depends on Scope
        var prWeight = scopeChanged
            ? PR_WEIGHTS_CHANGED[selections.PR]
            : PR_WEIGHTS_UNCHANGED[selections.PR];

        // Impact Sub-Score (ISS)
        var iss = 1 - ((1 - cWeight) * (1 - iWeight) * (1 - aWeight));

        // Impact
        var impact;
        if (scopeChanged) {
            impact = 7.52 * (iss - 0.029) - 3.25 * Math.pow(iss - 0.02, 15);
        } else {
            impact = 6.42 * iss;
        }

        // If impact is zero or negative, base score is 0.0
        if (impact <= 0) {
            return { score: 0.0, severity: 'none', vector: buildVector(selections) };
        }

        // Exploitability
        var exploitability = 8.22 * avWeight * acWeight * prWeight * uiWeight;

        // Base Score
        var baseScore;
        if (scopeChanged) {
            baseScore = roundUp(Math.min(1.08 * (impact + exploitability), 10.0));
        } else {
            baseScore = roundUp(Math.min(impact + exploitability, 10.0));
        }

        return {
            score: baseScore,
            severity: deriveSeverity(baseScore),
            vector: buildVector(selections)
        };
    }

    /**
     * Derive severity rating from score.
     */
    function deriveSeverity(score) {
        if (score === 0.0) return 'none';
        if (score <= 3.9) return 'low';
        if (score <= 6.9) return 'medium';
        if (score <= 8.9) return 'high';
        return 'critical';
    }

    /**
     * Build CVSS 3.1 vector string from selections.
     */
    function buildVector(selections) {
        var parts = [];
        for (var i = 0; i < METRICS.length; i++) {
            var key = METRICS[i].key;
            parts.push(key + ':' + selections[key]);
        }
        return 'CVSS:3.1/' + parts.join('/');
    }

    // ─── UI Rendering ──────────────────────────────────────────────────

    /**
     * Initialize the CVSS calculator on a container element.
     * @param {string|HTMLElement} container — Selector string or DOM element.
     * @param {object} [options] — Optional configuration.
     * @param {object} [options.initial] — Initial metric selections (e.g., { AV: 'N', AC: 'L', ... }).
     * @param {function} [options.onChange] — Callback invoked with { score, severity, vector } on change.
     */
    function init(container, options) {
        options = options || {};

        var el = typeof container === 'string'
            ? document.querySelector(container)
            : container;

        if (!el) {
            console.error('CvssCalculator: container not found', container);
            return null;
        }

        var selections = options.initial || {};
        var onChange = options.onChange || null;

        // Build the UI
        el.innerHTML = '';
        el.classList.add('cvss-calculator');

        // Metrics grid
        var grid = document.createElement('div');
        grid.className = 'cvss-metrics-grid';

        METRICS.forEach(function (metric) {
            var group = document.createElement('div');
            group.className = 'cvss-metric-group';

            var label = document.createElement('div');
            label.className = 'cvss-metric-label';
            label.textContent = metric.label;
            group.appendChild(label);

            var btnRow = document.createElement('div');
            btnRow.className = 'cvss-metric-buttons';

            metric.options.forEach(function (opt) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cvss-toggle-btn';
                btn.setAttribute('data-metric', metric.key);
                btn.setAttribute('data-value', opt.value);
                btn.setAttribute('aria-label', metric.label + ': ' + opt.label);
                btn.setAttribute('aria-pressed', 'false');
                btn.textContent = opt.label;

                if (selections[metric.key] === opt.value) {
                    btn.classList.add('cvss-toggle-btn--selected');
                    btn.setAttribute('aria-pressed', 'true');
                }

                btn.addEventListener('click', function () {
                    selectMetric(metric.key, opt.value, el, selections, onChange);
                });

                btnRow.appendChild(btn);
            });

            group.appendChild(btnRow);
            grid.appendChild(group);
        });

        el.appendChild(grid);

        // Result display area
        var resultArea = document.createElement('div');
        resultArea.className = 'cvss-result';

        var vectorDisplay = document.createElement('div');
        vectorDisplay.className = 'cvss-vector-display';
        vectorDisplay.innerHTML = '<span class="cvss-vector-label">Vector:</span> <code class="cvss-vector-string">—</code>';
        resultArea.appendChild(vectorDisplay);

        var scoreDisplay = document.createElement('div');
        scoreDisplay.className = 'cvss-score-display';
        scoreDisplay.innerHTML = '<span class="cvss-score-value">—</span><span class="cvss-severity-badge"></span>';
        resultArea.appendChild(scoreDisplay);

        el.appendChild(resultArea);

        // Hidden inputs for form submission
        var hiddenContainer = document.createElement('div');
        hiddenContainer.className = 'cvss-hidden-inputs';
        hiddenContainer.style.display = 'none';

        METRICS.forEach(function (metric) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cvss_' + metric.key.toLowerCase();
            input.setAttribute('data-cvss-input', metric.key);
            input.value = selections[metric.key] || '';
            hiddenContainer.appendChild(input);
        });

        el.appendChild(hiddenContainer);

        // Initial computation
        updateDisplay(el, selections, onChange);

        // Return public API for programmatic access
        return {
            getSelections: function () { return Object.assign({}, selections); },
            getResult: function () { return computeScore(selections); },
            setSelections: function (newSelections) {
                Object.keys(newSelections).forEach(function (key) {
                    selections[key] = newSelections[key];
                });
                refreshButtonStates(el, selections);
                updateDisplay(el, selections, onChange);
            },
            reset: function () {
                METRICS.forEach(function (m) { delete selections[m.key]; });
                refreshButtonStates(el, selections);
                updateDisplay(el, selections, onChange);
            }
        };
    }

    /**
     * Handle metric button selection.
     */
    function selectMetric(metricKey, value, container, selections, onChange) {
        selections[metricKey] = value;

        // Update button states for this metric group
        var buttons = container.querySelectorAll('[data-metric="' + metricKey + '"]');
        buttons.forEach(function (btn) {
            if (btn.getAttribute('data-value') === value) {
                btn.classList.add('cvss-toggle-btn--selected');
                btn.setAttribute('aria-pressed', 'true');
            } else {
                btn.classList.remove('cvss-toggle-btn--selected');
                btn.setAttribute('aria-pressed', 'false');
            }
        });

        updateDisplay(container, selections, onChange);
    }

    /**
     * Refresh all button states (used for programmatic updates).
     */
    function refreshButtonStates(container, selections) {
        METRICS.forEach(function (metric) {
            var buttons = container.querySelectorAll('[data-metric="' + metric.key + '"]');
            buttons.forEach(function (btn) {
                var val = btn.getAttribute('data-value');
                if (selections[metric.key] === val) {
                    btn.classList.add('cvss-toggle-btn--selected');
                    btn.setAttribute('aria-pressed', 'true');
                } else {
                    btn.classList.remove('cvss-toggle-btn--selected');
                    btn.setAttribute('aria-pressed', 'false');
                }
            });
        });
    }

    /**
     * Update the score display, vector string, severity badge, and hidden inputs.
     */
    function updateDisplay(container, selections, onChange) {
        var result = computeScore(selections);

        var vectorEl = container.querySelector('.cvss-vector-string');
        var scoreEl = container.querySelector('.cvss-score-value');
        var badgeEl = container.querySelector('.cvss-severity-badge');

        if (result) {
            vectorEl.textContent = result.vector;
            scoreEl.textContent = result.score.toFixed(1);

            // Update severity badge
            badgeEl.textContent = result.severity.charAt(0).toUpperCase() + result.severity.slice(1);
            badgeEl.className = 'cvss-severity-badge ' + SEVERITY_CLASSES[result.severity];
        } else {
            vectorEl.textContent = '—';
            scoreEl.textContent = '—';
            badgeEl.textContent = '';
            badgeEl.className = 'cvss-severity-badge';
        }

        // Update hidden form inputs
        METRICS.forEach(function (metric) {
            var input = container.querySelector('[data-cvss-input="' + metric.key + '"]');
            if (input) {
                input.value = selections[metric.key] || '';
            }
        });

        // Fire callback
        if (onChange && result) {
            onChange(result);
        }
    }

    /**
     * Parse an existing CVSS vector string and return metric selections.
     * @param {string} vector — e.g., "CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H"
     * @returns {object|null} Selections object or null if invalid.
     */
    function parseVector(vector) {
        if (!vector || typeof vector !== 'string') return null;

        vector = vector.trim();
        if (vector.indexOf('CVSS:3.1/') !== 0) return null;

        var metricString = vector.substring(9);
        var parts = metricString.split('/');
        if (parts.length !== 8) return null;

        var selections = {};
        for (var i = 0; i < parts.length; i++) {
            var pair = parts[i].split(':');
            if (pair.length !== 2) return null;
            selections[pair[0]] = pair[1];
        }

        // Validate all metrics present
        for (var j = 0; j < METRICS.length; j++) {
            if (!selections[METRICS[j].key]) return null;
        }

        return selections;
    }

    // ─── Public API ────────────────────────────────────────────────────

    return {
        init: init,
        computeScore: computeScore,
        parseVector: parseVector,
        buildVector: buildVector,
        deriveSeverity: deriveSeverity
    };

})();
