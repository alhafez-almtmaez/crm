<?php

use App\Services\Admin\AbsenceRules\MessageTemplateRenderer;

test('message template renderer replaces single and double brace placeholders', function () {
    $renderer = new MessageTemplateRenderer;

    $message = $renderer->render(
        'تنبيه للطالب: *{ student.full_name }* - {{attendance.label_ar}}',
        [
            'student' => ['full_name' => 'موسى يوسف'],
            'attendance' => ['label_ar' => 'غياب بعذر'],
        ],
    );

    expect($message)->toBe('تنبيه للطالب: *موسى يوسف* - غياب بعذر');
});
