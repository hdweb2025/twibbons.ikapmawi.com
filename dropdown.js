/**
 * Modern Custom Searchable Dropdown for Alumni Year Selection
 * IKAPMAWI Twibbon App
 */
document.addEventListener('DOMContentLoaded', () => {
    const wrappers = document.querySelectorAll('.custom-select-wrapper');

    wrappers.forEach(wrapper => {
        const trigger = wrapper.querySelector('.custom-select-trigger');
        const textSpan = wrapper.querySelector('.custom-select-text');
        const hiddenInput = wrapper.querySelector('input[type="hidden"]');
        const searchInput = wrapper.querySelector('.custom-select-search-input');
        const optionsList = wrapper.querySelector('.custom-select-options');
        const options = wrapper.querySelectorAll('.custom-select-option');
        const noResults = wrapper.querySelector('.custom-select-no-results');

        // Toggle open/close dropdown
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            // Close any other open dropdowns
            wrappers.forEach(w => {
                if (w !== wrapper) w.classList.remove('open');
            });

            const isOpen = wrapper.classList.toggle('open');
            if (isOpen && searchInput) {
                setTimeout(() => searchInput.focus(), 80);
            }
        });

        // Search Filter Logic
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                let hasVisible = false;

                options.forEach(opt => {
                    const val = opt.getAttribute('data-value').toLowerCase();
                    const text = opt.textContent.toLowerCase();
                    if (val.includes(query) || text.includes(query)) {
                        opt.style.display = 'flex';
                        hasVisible = true;
                    } else {
                        opt.style.display = 'none';
                    }
                });

                // Handle decade group headers visibility
                const decades = wrapper.querySelectorAll('.custom-select-decade');
                decades.forEach(dec => {
                    let next = dec.nextElementSibling;
                    let anyInDecade = false;
                    while (next && next.classList.contains('custom-select-option')) {
                        if (next.style.display !== 'none') {
                            anyInDecade = true;
                            break;
                        }
                        next = next.nextElementSibling;
                    }
                    dec.style.display = anyInDecade ? 'block' : 'none';
                });

                if (noResults) {
                    noResults.style.display = hasVisible ? 'none' : 'block';
                }
            });

            // Prevent closing dropdown when clicking inside search input
            searchInput.addEventListener('click', (e) => e.stopPropagation());
        }

        // Option selection
        options.forEach(opt => {
            opt.addEventListener('click', (e) => {
                e.stopPropagation();
                const value = opt.getAttribute('data-value');
                const label = opt.getAttribute('data-label') || value;

                // Update hidden input for form submit
                hiddenInput.value = value;

                // Trigger change event for validation
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));

                // Update trigger text & class
                textSpan.textContent = 'Alumni ' + label;
                trigger.classList.remove('placeholder');

                // Mark selected option
                options.forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');

                // Close dropdown
                wrapper.classList.remove('open');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input'));
                }
            });
        });
    });

    // Close when clicking outside
    document.addEventListener('click', () => {
        wrappers.forEach(w => w.classList.remove('open'));
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            wrappers.forEach(w => w.classList.remove('open'));
        }
    });
});
