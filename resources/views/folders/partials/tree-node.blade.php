@php
    $children = $node->relationLoaded('treeChildren') ? $node->getRelation('treeChildren') : collect();
    $hasChildren = $children->isNotEmpty();
    $isActive = $currentFolder !== null && (int) $currentFolder->id === (int) $node->id;
    $isExpanded = in_array((int) $node->id, $expandedFolderIds, true);
    $activeDocsCount = (int) ($node->active_documents_count ?? 0);
@endphp

<li class="sdr-tree-item"
    data-tree-item
    data-folder-id="{{ $node->id }}"
    data-folder-name="{{ mb_strtolower($node->name) }}">
    <div class="sdr-tree-row {{ $isActive ? 'is-active' : '' }}">
        @if ($hasChildren)
            <button type="button"
                    class="sdr-tree-toggle"
                    data-tree-toggle
                    aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                    aria-label="Expandir o contraer {{ $node->name }}"
                    title="Expandir o contraer">
                <span class="sdr-tree-chevron" data-tree-chevron>{{ $isExpanded ? '▾' : '▸' }}</span>
            </button>
        @else
            <span class="sdr-tree-spacer" aria-hidden="true"></span>
        @endif

        <a href="{{ route('folders.show', $node) }}"
           class="sdr-tree-link {{ $isActive ? 'is-active' : '' }}"
           title="{{ $node->name }}">
            <i data-lucide="{{ $isActive || ($hasChildren && $isExpanded) ? 'folder-open' : 'folder' }}"
               class="ta-icon-xs sdr-tree-icon"></i>
            <span class="sdr-tree-name">{{ $node->name }}</span>
            @if ($activeDocsCount > 0)
                <span class="sdr-tree-count" title="{{ $activeDocsCount }} archivos activos">{{ $activeDocsCount }}</span>
            @endif
        </a>
    </div>

    @if ($hasChildren)
        <ul class="sdr-tree-children {{ $isExpanded ? '' : 'd-none' }}"
            data-tree-children
            data-initial-expanded="{{ $isExpanded ? '1' : '0' }}">
            @foreach ($children as $childNode)
                @include('folders.partials.tree-node', [
                    'node' => $childNode,
                    'currentFolder' => $currentFolder,
                    'expandedFolderIds' => $expandedFolderIds,
                    'level' => ($level ?? 0) + 1,
                ])
            @endforeach
        </ul>
    @endif
</li>
