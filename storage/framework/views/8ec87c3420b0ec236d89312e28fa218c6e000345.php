<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    
    <title><?php echo $__env->yieldContent('title'); ?></title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

    <!-- Styles -->
    <link rel="stylesheet" href="https://unpkg.com/@vuepic/vue-datepicker@latest/dist/main.css">
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
    <link rel="stylesheet" href="https://unpkg.com/vue-select@latest/dist/vue-select.css">

    <!-- Scripts -->
    <script src="https://unpkg.com/vue@latest"></script>
    <script src="https://unpkg.com/@vuepic/vue-datepicker@latest"></script>
    
    <script type="text/javascript" src="https://unpkg.com/xlsx@0.15.1/dist/xlsx.full.min.js"></script>
    <script type="text/javascript">
        window.Laravel = {
            csrfToken: "<?php echo e(csrf_token()); ?>",
            jsPermissions: <?php echo auth()->check()
                ? auth()->user()->jsPermissions()
                : 0; ?>

        }
    </script>
    <script src="https://unpkg.com/vue-select@latest"></script>
    <script src="<?php echo e(asset('js/app.js')); ?>" defer></script>
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-blue-800 w-full" id="app">
        <?php echo $__env->make('layouts.navigation', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        <!-- Page Heading -->
        

        <!-- Page Content -->
        <?php if(Auth::check()): ?>
            <meta name="user-id" content="<?php echo e(Auth::user()->id); ?>" />
            <meta name="user_roles" content="<?php echo e(Auth::user()->roles); ?>" />
            <meta name="user_permissions" content="<?php echo e(Auth::user()->permissions); ?>" />
        <?php endif; ?>
        <main>
            <?php echo e($slot); ?>

        </main>
    </div>
</body>

</html>
<?php /**PATH D:\xampp\htdocs\CRM_BGOC\resources\views/layouts/app.blade.php ENDPATH**/ ?>