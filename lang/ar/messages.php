<?php

return [
    'broker_unavailable' => 'خادم الرسائل (RabbitMQ) غير متاح. شغّل: docker compose up -d rabbitmq. إذا كان التطبيق داخل Docker استخدم المضيف rabbitmq وليس 127.0.0.1 — أعد تشغيل الحاوية بعد php artisan config:clear.',
    'auth' => [
        'registered' => 'تم التسجيل بنجاح قم بإدخال كود التفعيل',
        'logged_in' => 'تم تسجيل الدخول بنجاح.',
        'logged_out' => 'تم تسجيل الخروج بنجاح.',
        'profile' => 'بيانات المستخدم.',
        'profile_updated' => 'تم تحديث الملف الشخصي بنجاح.',
    ],
    'order' => [
        'created' => 'تم إنشاء الطلب بنجاح.',
    ],
    'errors' => [
        'generic' => 'حدث خطأ. يرجى المحاولة مرة أخرى.',
        'forbidden' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
        'not_found' => 'المورد غير موجود.',
        'validation_failed' => 'فشل التحقق من البيانات.',
    ],
];
