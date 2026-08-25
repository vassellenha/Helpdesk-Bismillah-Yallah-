{{--
    Satu berkas untuk lima peran. Layout-nya yang berbeda, dan itu datang dari
    controller — jadi lonceng, menu, dan grup terjemahan tiap peran tetap
    seperti halaman lain di peran itu.
--}}
@extends($layout)

@section('title', __('common.notifications.title'))

@section('content')
<div
    data-react="NotificationHistoryPage"
    data-props="{{ json_encode([
        'items' => $history['items'],
        'page' => $history['page'],
        'lastPage' => $history['lastPage'],
        'total' => $history['total'],
        'unreadCount' => $notifications['unreadCount'],
        'markAllReadUrl' => $markAllReadUrl,
    ]) }}"
></div>
@endsection
