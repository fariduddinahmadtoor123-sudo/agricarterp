@once
    <script>
        function agricartScoreEntry(entry, terms, query) {
            const label = entry.label.toLowerCase();
            const breadcrumb = entry.breadcrumb.toLowerCase();
            const module = entry.module.toLowerCase();
            const keywords = entry.keywords;
            let score = 0;

            if (label === query) {
                score += 120;
            } else if (label.startsWith(query)) {
                score += 95;
            } else if (label.includes(query)) {
                score += 75;
            }

            if (breadcrumb.includes(query)) {
                score += 45;
            }

            if (module.startsWith(query)) {
                score += 40;
            }

            for (const term of terms) {
                if (! term) {
                    continue;
                }

                if (label === term) {
                    score += 80;
                } else if (label.startsWith(term)) {
                    score += 60;
                } else if (label.includes(term)) {
                    score += 50;
                }

                if (keywords.includes(term)) {
                    score += 25;
                }

                if (module.includes(term)) {
                    score += 15;
                }
            }

            return score;
        }

        function agricartSearchEntries(entries, query, limit = 12) {
            const normalized = query.trim().toLowerCase();

            if (! normalized) {
                return [];
            }

            const terms = normalized.split(/\s+/).filter(Boolean);
            const scored = [];

            for (const entry of entries) {
                const score = agricartScoreEntry(entry, terms, normalized);

                if (score > 0) {
                    scored.push({ entry, score });
                }
            }

            scored.sort((left, right) => {
                if (left.score !== right.score) {
                    return right.score - left.score;
                }

                return left.entry.breadcrumb.localeCompare(right.entry.breadcrumb);
            });

            return scored.slice(0, limit).map((row) => row.entry);
        }

        function agricartGlobalSearch(entries) {
            return {
                entries,
                open: false,
                query: '',
                mobileOpen: false,
                activeIndex: -1,
                hasQuery() {
                    return this.query.trim().length > 0;
                },
                results() {
                    return agricartSearchEntries(this.entries, this.query);
                },
                showDropdown() {
                    return this.open && this.hasQuery();
                },
                resetActiveIndex() {
                    this.activeIndex = -1;
                },
                openMobile() {
                    this.mobileOpen = true;
                    this.open = true;
                    this.$nextTick(() => this.$refs.mobileInput?.focus());
                },
                closeAll() {
                    this.open = false;
                    this.mobileOpen = false;
                    this.activeIndex = -1;
                },
                selectResult(index) {
                    const result = this.results()[index];

                    if (! result) {
                        return;
                    }

                    window.location.assign(result.url);
                },
                handleKeydown(event) {
                    if (! this.hasQuery()) {
                        return;
                    }

                    const count = this.results().length;

                    if (count === 0) {
                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        this.activeIndex = this.activeIndex >= count - 1 ? 0 : this.activeIndex + 1;
                    }

                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        this.activeIndex = this.activeIndex <= 0 ? count - 1 : this.activeIndex - 1;
                    }

                    if (event.key === 'Enter' && this.activeIndex >= 0) {
                        event.preventDefault();
                        this.selectResult(this.activeIndex);
                    }
                },
            };
        }
    </script>
@endonce
