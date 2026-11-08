<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ __('homeworks.certificate_print_title') }}</title>
    <link rel="stylesheet" href="{{ $stylesheet }}">
    <style>
        @foreach ($sharedImageClasses as $source => $class)
            .{{ $class }} { content: url("{{ $source }}"); }
        @endforeach

        @media print {
            html, body.certificate-batch { height: auto; min-height: 0; overflow: visible; }
            .certificate-batch > .certificate-page + .certificate-page { break-before: page; }
        }
    </style>
</head>
<body class="certificate-batch">
    @foreach ($certificates as $certificate)
        @include('certificates.partials.page', ['certificate' => $certificate])
    @endforeach
</body>
</html>
