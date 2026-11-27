<div class="ta-card sdr-tree-card h-100">
    <div class="ta-card-header py-3">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <h2 class="ta-card-title mb-0" style="font-size: 0.95rem;">Estructura de carpetas</h2>
            <a href="{{ route('folders.index') }}"
               class="btn btn-sm {{ $currentFolder === null ? 'btn-primary' : 'btn-outline-secondary' }}"
               style="height: 28px; padding: 0 10px; font-size: 0.75rem;">
                Raíz
            </a>
        </div>

        <div class="mt-2">
            <input id="sdr-tree-filter"
                   type="search"
                   class="form-control form-control-sm"
                   placeholder="Filtrar carpeta..."
                   aria-label="Filtrar carpeta">
        </div>
    </div>

    <div class="ta-card-body p-2 sdr-tree-body">
        @if ($folderTree->isEmpty())
            <p class="small text-center py-4 mb-0" style="color: var(--ta-text-muted);">
                Sin carpetas autorizadas.
            </p>
        @else
            <ul class="sdr-tree-root" id="sdr-folder-tree">
                @foreach ($folderTree as $rootNode)
                    @include('folders.partials.tree-node', [
                        'node' => $rootNode,
                        'currentFolder' => $currentFolder,
                        'expandedFolderIds' => $expandedFolderIds,
                        'level' => 0,
                    ])
                @endforeach
            </ul>
            <p id="sdr-tree-empty" class="small text-center py-3 mb-0 d-none" style="color: var(--ta-text-muted);">
                No hay carpetas coincidentes.
            </p>
        @endif
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const treeRoot = document.getElementById('sdr-folder-tree');
                const filterInput = document.getElementById('sdr-tree-filter');
                const emptyNotice = document.getElementById('sdr-tree-empty');

                if (!treeRoot) {
                    return;
                }

                const setBranchExpanded = (item, expanded) => {
                    const childrenList = item.querySelector(':scope > [data-tree-children]');
                    const toggleBtn = item.querySelector(':scope > .sdr-tree-row [data-tree-toggle]');
                    const chevron = toggleBtn ? toggleBtn.querySelector('[data-tree-chevron]') : null;

                    if (childrenList) {
                        childrenList.classList.toggle('d-none', !expanded);
                    }
                    if (toggleBtn) {
                        toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    }
                    if (chevron) {
                        chevron.textContent = expanded ? '▾' : '▸';
                    }
                };

                treeRoot.addEventListener('click', (event) => {
                    const toggleBtn = event.target.closest('[data-tree-toggle]');
                    if (!toggleBtn) {
                        return;
                    }

                    const item = toggleBtn.closest('[data-tree-item]');
                    const childrenList = item ? item.querySelector(':scope > [data-tree-children]') : null;
                    if (!item || !childrenList) {
                        return;
                    }

                    const isCurrentlyHidden = childrenList.classList.contains('d-none');
                    setBranchExpanded(item, isCurrentlyHidden);
                });

                if (filterInput) {
                    const filterNode = (item, query) => {
                        const name = (item.dataset.folderName || '').toLowerCase();
                        const selfMatches = name.includes(query);
                        const childrenList = item.querySelector(':scope > [data-tree-children]');
                        const childItems = childrenList
                            ? Array.from(childrenList.querySelectorAll(':scope > [data-tree-item]'))
                            : [];

                        let anyChildMatches = false;
                        for (const childItem of childItems) {
                            if (filterNode(childItem, query)) {
                                anyChildMatches = true;
                            }
                        }

                        const visible = query === '' || selfMatches || anyChildMatches;
                        item.classList.toggle('d-none', !visible);

                        if (childrenList) {
                            if (query === '') {
                                const initialExpanded = childrenList.dataset.initialExpanded === '1';
                                setBranchExpanded(item, initialExpanded);
                            } else {
                                setBranchExpanded(item, anyChildMatches);
                            }
                        }

                        return visible;
                    };

                    filterInput.addEventListener('input', () => {
                        const query = filterInput.value.trim().toLowerCase();
                        const topItems = Array.from(treeRoot.querySelectorAll(':scope > [data-tree-item]'));
                        let visibleCount = 0;

                        for (const item of topItems) {
                            if (filterNode(item, query)) {
                                visibleCount++;
                            }
                        }

                        if (emptyNotice) {
                            emptyNotice.classList.toggle('d-none', visibleCount > 0);
                        }
                    });
                }
            });
        </script>
    @endpush
@endonce
