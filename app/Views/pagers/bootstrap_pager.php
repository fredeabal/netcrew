<?php $pager->setSurroundCount(2); ?>

<nav aria-label="Paginación de usuarios">
    <ul class="pagination pagination-sm mb-0 gap-1">
        <?php if ($pager->hasPreviousPage()) : ?>
            <li class="page-item">
                <a class="page-link rounded-circle d-flex align-items-center justify-content-center" href="<?= $pager->getFirst() ?>" aria-label="Primera">
                    <i class="ti ti-chevrons-left fs-5"></i>
                </a>
            </li>
            <li class="page-item">
                <a class="page-link rounded-circle d-flex align-items-center justify-content-center" href="<?= $pager->getPreviousPage() ?>" aria-label="Anterior">
                    <i class="ti ti-chevron-left fs-5"></i>
                </a>
            </li>
        <?php endif ?>

        <?php foreach ($pager->links() as $link) : ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                <a class="page-link rounded-circle d-flex align-items-center justify-content-center" href="<?= $link['uri'] ?>">
                    <?= $link['title'] ?>
                </a>
            </li>
        <?php endforeach ?>

        <?php if ($pager->hasNextPage()) : ?>
            <li class="page-item">
                <a class="page-link rounded-circle d-flex align-items-center justify-content-center" href="<?= $pager->getNextPage() ?>" aria-label="Siguiente">
                    <i class="ti ti-chevron-right fs-5"></i>
                </a>
            </li>
            <li class="page-item">
                <a class="page-link rounded-circle d-flex align-items-center justify-content-center" href="<?= $pager->getLast() ?>" aria-label="Última">
                    <i class="ti ti-chevrons-right fs-5"></i>
                </a>
            </li>
        <?php endif ?>
    </ul>
</nav>
