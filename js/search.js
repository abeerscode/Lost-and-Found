// AJAX search/filter requests against posts/search.php (FR-3.x).
(function () {
    const form = document.getElementById('filter-form');
    const resultsEl = document.getElementById('feed-results');
    const statusEl = document.getElementById('feed-status');
    const paginationEl = document.getElementById('feed-pagination');
    const template = document.getElementById('post-card-template');

    if (!form) return;

    let currentPage = 1;
    let debounceTimer = null;

    function buildQuery(page) {
        const params = new URLSearchParams();
        const fields = ['q', 'type', 'category_id', 'status', 'location', 'date_from', 'date_to', 'sort'];
        fields.forEach((name) => {
            const el = form.querySelector(`[name="${name}"]`);
            if (el && el.value) params.set(name, el.value);
        });
        params.set('page', page);
        return params.toString();
    }

    function renderCard(post) {
        const node = template.content.cloneNode(true);
        const card = node.querySelector('.post-card');
        card.href = post.view_url;

        const photo = node.querySelector('.post-card-photo');
        if (post.photo_url) {
            photo.style.backgroundImage = `url('${post.photo_url}')`;
        } else {
            photo.textContent = '📦';
            photo.style.display = 'flex';
            photo.style.alignItems = 'center';
            photo.style.justifyContent = 'center';
            photo.style.fontSize = '2rem';
        }

        const typeTag = node.querySelector('.type-tag');
        typeTag.textContent = post.type === 'lost' ? 'Lost' : 'Found';
        typeTag.classList.add(post.type === 'lost' ? 'type-lost' : 'type-found');

        const statusTag = node.querySelector('.status-tag');
        statusTag.innerHTML = `<span class="badge badge-${post.status}">${post.status.charAt(0).toUpperCase() + post.status.slice(1)}</span>`;
        if (post.is_high_value) {
            statusTag.innerHTML += ' <span class="badge badge-highvalue">HV</span>';
        }

        node.querySelector('.post-card-title').textContent = post.title;
        node.querySelector('.post-card-meta').textContent =
            `${post.category} · ${post.location} · ${post.time_ago}`;
        node.querySelector('.post-card-desc').textContent = post.description;

        return node;
    }

    function renderPagination(page, totalPages) {
        paginationEl.innerHTML = '';
        if (totalPages <= 1) return;
        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            if (i === page) btn.classList.add('active');
            btn.addEventListener('click', () => { currentPage = i; fetchResults(); });
            paginationEl.appendChild(btn);
        }
    }

    function fetchResults() {
        statusEl.textContent = 'Searching…';
        fetch(`${window.APP_BASE_URL || ''}/posts/search.php?${buildQuery(currentPage)}`)
            .then((res) => res.json())
            .then((data) => {
                if (data.error) {
                    statusEl.textContent = data.error;
                    resultsEl.innerHTML = '';
                    return;
                }
                resultsEl.innerHTML = '';
                if (!data.results.length) {
                    resultsEl.innerHTML = '<p class="muted">No posts match your search.</p>';
                } else {
                    data.results.forEach((post) => resultsEl.appendChild(renderCard(post)));
                }
                statusEl.textContent = `${data.total} post${data.total === 1 ? '' : 's'} found`;
                renderPagination(data.page, data.total_pages);
            })
            .catch(() => {
                statusEl.textContent = 'Something went wrong loading posts.';
            });
    }

    form.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => { currentPage = 1; fetchResults(); }, 350);
    });
    form.addEventListener('change', () => { currentPage = 1; fetchResults(); });

    const advancedToggle = document.getElementById('advanced-filter-toggle');
    const advancedFilters = document.getElementById('advanced-filters');
    if (advancedToggle && advancedFilters) {
        advancedToggle.addEventListener('click', () => {
            const willOpen = advancedFilters.hidden;
            advancedFilters.hidden = !willOpen;
            advancedToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    }

    const categorySelect = document.getElementById('f-category');
    const categoryChips = Array.from(document.querySelectorAll('.category-chip'));
    function syncCategoryChips() {
        if (!categorySelect) return;
        categoryChips.forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.category === categorySelect.value);
        });
    }
    function updateCategoryUrl(value) {
        if (!window.history || !window.history.replaceState) return;
        const url = new URL(window.location.href);
        if (value) url.searchParams.set('category_id', value);
        else url.searchParams.delete('category_id');
        window.history.replaceState({}, '', url);
    }

    categoryChips.forEach((chip) => {
        chip.addEventListener('click', (event) => {
            // Keep the href as a no-JS fallback, but handle it instantly when JS is available.
            event.preventDefault();
            if (!categorySelect) {
                window.location.href = chip.href;
                return;
            }

            const value = chip.dataset.category || '';
            categorySelect.value = value;
            syncCategoryChips();
            updateCategoryUrl(value);
            currentPage = 1;
            fetchResults();
        });
    });
    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            syncCategoryChips();
            updateCategoryUrl(categorySelect.value);
        });
    }

    const clearFilters = document.getElementById('clear-filters');
    if (clearFilters) {
        clearFilters.addEventListener('click', () => {
            form.reset();
            syncCategoryChips();
            currentPage = 1;
            fetchResults();
        });
    }

    syncCategoryChips();
    fetchResults();
})();
