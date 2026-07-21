<?php $pager->setSurroundCount(1) ?>

<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center mb-0">
        <!-- Primero -->
        <li class="page-item <?= $pager->hasPrevious() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $pager->hasPrevious() ? $pager->getFirst() : 'javascript:void(0)' ?>" aria-label="First">
                <i class="ti ti-chevrons-left"></i>
            </a>
        </li>
        
        <!-- Anterior -->
        <li class="page-item <?= $pager->hasPrevious() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $pager->hasPrevious() ? $pager->getPrevious() : 'javascript:void(0)' ?>" aria-label="Previous">
                <i class="ti ti-chevron-left"></i>
            </a>
        </li>

        <!-- Números (Limitados por setSurroundCount(1)) -->
        <?php foreach ($pager->links() as $link) : ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $link['uri'] ?>">
                    <?= $link['title'] ?>
                </a>
            </li>
        <?php endforeach ?>

        <!-- Siguiente -->
        <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $pager->hasNext() ? $pager->getNext() : 'javascript:void(0)' ?>" aria-label="Next">
                <i class="ti ti-chevron-right"></i>
            </a>
        </li>

        <!-- Último -->
        <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $pager->hasNext() ? $pager->getLast() : 'javascript:void(0)' ?>" aria-label="Last">
                <i class="ti ti-chevrons-right"></i>
            </a>
        </li>
    </ul>
</nav>
