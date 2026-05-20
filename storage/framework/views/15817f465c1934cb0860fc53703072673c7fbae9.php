<?php $__env->startSection('title'); ?>
    CRM-BGOC
<?php $__env->stopSection(); ?>

<?php if (isset($component)) { $__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\AppLayout::class, []); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
    

    <div class="py-2">
        <div class="mx-auto sm:px-6 lg:px-2 w-full md:px-3">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg ">
                <div class="p-2 bg-yellow-100 border-b">
                    <router-view />
                </div>
            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da)): ?>
<?php $component = $__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da; ?>
<?php unset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da); ?>
<?php endif; ?>
<?php /**PATH D:\xampp\htdocs\CRM_BGOC\resources\views/contact.blade.php ENDPATH**/ ?>