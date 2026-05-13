/**
 * Wordle Solver JavaScript - Premium Implementation
 */
class WordleSolver {
    constructor() {
        this.allWords = [];
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.cacheElements();
            this.bindEvents();
            this.loadData();
        });
    }

    cacheElements() {
        this.container = document.getElementById('wordle-solver');
        if (!this.container) return;

        this.greenGrid = document.getElementById('ws-green-grid');
        this.yellowGridsContainer = document.getElementById('ws-yellow-grids');
        this.grayGridsContainer = document.getElementById('ws-gray-grids');
        
        this.excludeYellowToggle = document.getElementById('ws-exclude-yellow');
        this.solveBtn = document.getElementById('ws-solve-btn');
        this.clearAllBtn = document.getElementById('ws-clear-all');
        
        this.resultsSection = document.getElementById('ws-results-section');
        this.resultsList = document.getElementById('ws-results-list');
        this.resultCount = document.getElementById('ws-result-count');
        this.loader = document.getElementById('ws-loader');

        this.alert = document.getElementById('ws-alert');
        this.alertClose = this.container.querySelector('.ws-alert-close');
    }

    bindEvents() {
        if (!this.container) return;

        // Auto-tab and key handling for all boxes
        this.container.addEventListener('input', (e) => {
            if (e.target.classList.contains('ws-box')) {
                e.target.value = e.target.value.toUpperCase().replace(/[^A-Z]/g, '');
                if (e.target.value.length === 1) {
                    this.focusNext(e.target);
                }
            }
        });

        this.container.addEventListener('keydown', (e) => {
            if (e.target.classList.contains('ws-box')) {
                if (e.key === 'Backspace' && !e.target.value) {
                    this.focusPrev(e.target);
                }
            }
        });

        // Add Row buttons
        this.container.querySelectorAll('.ws-add-row').forEach(btn => {
            btn.addEventListener('click', () => this.addRow(btn.dataset.target));
        });

        // Clear Section buttons
        this.container.querySelectorAll('.ws-clear-section').forEach(btn => {
            btn.addEventListener('click', () => this.clearSection(btn.dataset.target));
        });

        this.solveBtn.addEventListener('click', () => this.solve());
        this.clearAllBtn.addEventListener('click', () => this.clearAll());
        
        if (this.alertClose) {
            this.alertClose.addEventListener('click', () => this.showAlert(false));
        }
    }

    async loadData() {
        this.showLoader(true);
        try {
            const response = await fetch(wordleSolverData.jsonUrl);
            const data = await response.json();
            this.allWords = data.words || [];
        } catch (error) {
            console.error('Wordle Solver Error:', error);
        } finally {
            this.showLoader(false);
        }
    }

    addRow(target) {
        const container = target === 'yellow' ? this.yellowGridsContainer : this.grayGridsContainer;
        const firstGrid = container.querySelector('.ws-grid');
        const newGrid = firstGrid.cloneNode(true);
        
        // Clear values in new grid
        newGrid.querySelectorAll('input').forEach(input => input.value = '');
        
        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'ws-row-wrapper';
        wrapper.appendChild(newGrid);

        // Add delete button
        const delBtn = document.createElement('button');
        delBtn.className = 'ws-row-delete';
        delBtn.innerHTML = '−';
        delBtn.onclick = () => wrapper.remove();
        
        wrapper.appendChild(delBtn);
        container.appendChild(wrapper);
    }

    clearSection(target) {
        if (target === 'green') {
            this.greenGrid.querySelectorAll('input').forEach(i => i.value = '');
        } else if (target === 'yellow') {
            this.yellowGridsContainer.querySelectorAll('input').forEach(i => i.value = '');
        } else if (target === 'gray') {
            this.grayGridsContainer.querySelectorAll('input').forEach(i => i.value = '');
        }
    }

    clearAll() {
        this.container.querySelectorAll('input[type="text"]').forEach(i => i.value = '');
        this.resultsSection.style.display = 'none';
        // Reset rows to 1
        while (this.yellowGridsContainer.children.length > 1) this.yellowGridsContainer.lastChild.remove();
        while (this.grayGridsContainer.children.length > 1) this.grayGridsContainer.lastChild.remove();
    }

    focusNext(el) {
        const inputs = Array.from(this.container.querySelectorAll('.ws-box'));
        const index = inputs.indexOf(el);
        if (index > -1 && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
    }

    focusPrev(el) {
        const inputs = Array.from(this.container.querySelectorAll('.ws-box'));
        const index = inputs.indexOf(el);
        if (index > 0) {
            inputs[index - 1].focus();
        }
    }

    solve() {
        if (this.allWords.length === 0) return;

        // 0. Validation: Must have at least one letter
        const allInputs = Array.from(this.container.querySelectorAll('.ws-box'));
        const hasValue = allInputs.some(i => i.value.trim() !== '');
        
        if (!hasValue) {
            this.resultsSection.style.display = 'none';
            this.showAlert(true);
            return;
        }

        this.showAlert(false);
        this.showLoader(true);

        // 1. Gather Constraints
        const green = Array.from(this.greenGrid.querySelectorAll('input')).map(i => i.value || null);
        
        // Yellow Constraints: { char: 'A', excludePos: [0, 2] }
        const yellowConstraints = [];
        const yellowGrids = this.yellowGridsContainer.querySelectorAll('.ws-grid');
        yellowGrids.forEach(grid => {
            const inputs = grid.querySelectorAll('input');
            inputs.forEach((input, pos) => {
                const val = input.value;
                if (val) {
                    let existing = yellowConstraints.find(c => c.char === val);
                    if (!existing) {
                        existing = { char: val, excludePos: [] };
                        yellowConstraints.push(existing);
                    }
                    if (this.excludeYellowToggle.checked) {
                        existing.excludePos.push(pos);
                    }
                }
            });
        });

        // Gray Constraints: ['O', 'T', 'S']
        const gray = Array.from(this.grayGridsContainer.querySelectorAll('input'))
            .map(i => i.value)
            .filter(v => v !== '');

        // 2. Filter
        const filtered = this.allWords.filter(word => {
            const letters = word.split('');

            // GREEN
            for (let i = 0; i < 5; i++) {
                if (green[i] && letters[i] !== green[i]) return false;
            }

            // YELLOW
            for (const c of yellowConstraints) {
                // Must contain
                if (!letters.includes(c.char)) return false;
                // Must NOT be in excluded positions
                for (const pos of c.excludePos) {
                    if (letters[pos] === c.char) return false;
                }
            }

            // GRAY
            for (const char of gray) {
                // If gray letter is in word, it must be because it's already green or yellow elsewhere
                if (letters.includes(char)) {
                    // Check if this instance of 'char' is justified by green or yellow
                    const isJustified = green.includes(char) || yellowConstraints.some(yc => yc.char === char);
                    if (!isJustified) return false;
                    
                    // Advanced: if char is both green/yellow and gray, it means it only appears N times.
                    // For now, simpler: if it's gray and not in green/yellow, exclude.
                }
            }

            return true;
        });

        this.renderResults(filtered);
        this.showLoader(false);
    }

    renderResults(words) {
        this.resultsList.innerHTML = '';
        this.resultCount.textContent = `${words.length} words found`;
        
        if (words.length === 0) {
            this.resultsList.innerHTML = '<div style="width:100%; text-align:center; padding:20px; color:#666;">No matches. Try different letters.</div>';
        } else {
            words.forEach(word => {
                const div = document.createElement('div');
                div.className = 'ws-word-item';
                div.textContent = word;
                this.resultsList.appendChild(div);
            });
        }
        this.resultsSection.style.display = 'block';
    }

    showLoader(show) {
        this.loader.style.display = show ? 'block' : 'none';
        this.solveBtn.disabled = show;
    }

    showAlert(show) {
        if (!this.alert) return;
        
        if (show) {
            this.alert.style.display = 'flex';
            // Auto hide after 3 seconds
            if (this.alertTimeout) clearTimeout(this.alertTimeout);
            this.alertTimeout = setTimeout(() => {
                this.showAlert(false);
            }, 3000);
        } else {
            this.alert.style.display = 'none';
        }
    }
}

new WordleSolver();
