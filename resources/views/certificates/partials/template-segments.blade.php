@php
    foreach ($segments as $segment) {
        $segmentType = ($segment['type'] ?? null) === 'variable' ? 'variable' : 'text';
        $variableKey = $segmentType === 'variable' && in_array($segment['key'] ?? null, [
            'student_name',
            'center_name',
            'achievement_label',
            'achievement_name',
            'certificate_number',
            'plan_name',
            'plan_point_name',
            'hijri_date',
            'gregorian_date',
        ], true) ? (string) $segment['key'] : null;
        $tag = in_array($variableKey, ['student_name', 'center_name', 'achievement_name'], true)
            ? 'strong'
            : 'span';
        $attribute = $variableKey !== null
            ? ' data-certificate-template-variable="'.e($variableKey).'"'
            : '';

        echo '<'.$tag.$attribute.'>'.e((string) ($segment['text'] ?? '')).'</'.$tag.'>';
    }
@endphp
