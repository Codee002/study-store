<?php

return [
    'about' => [
        'name' => env('CHATBOX_SYSTEM_NAME', 'Trợ lý AI cửa hàng văn phòng phẩm'),
        'owner' => env('CHATBOX_SYSTEM_OWNER', 'Nhóm phát triển hệ thống'),
        'purpose' => env('CHATBOX_SYSTEM_PURPOSE', 'Hỗ trợ tư vấn sản phẩm, đơn hàng, giỏ hàng, tài khoản và khuyến mãi cho khách hàng.'),
        'academic_purpose' => env('CHATBOX_SYSTEM_ACADEMIC_PURPOSE', 'Hệ thống được xây dựng phục vụ mục tiêu học thuật và triển khai trong bối cảnh luận văn tốt nghiệp, nhằm nghiên cứu cách kết hợp AI hội thoại, semantic search, gợi ý sản phẩm và truy xuất dữ liệu nghiệp vụ trong thương mại điện tử văn phòng phẩm.'),
        'system_functions' => [
            'Tư vấn và gợi ý sản phẩm phù hợp',
            'Tra cứu đơn hàng của chính khách hàng',
            'Hiển thị thông tin giỏ hàng hiện tại',
            'Cung cấp thông tin tài khoản cá nhân',
            'Tra cứu khuyến mãi đang áp dụng',
            'Giải đáp chính sách và thông tin chung về hệ thống',
        ],
        'supported_topics' => [
            'sản phẩm',
            'đơn hàng của khách hàng',
            'giỏ hàng hiện tại',
            'thông tin tài khoản',
            'khuyến mãi đang áp dụng',
            'chính sách và thông tin hệ thống',
        ],
    ],
    'policy' => [
        'doi_tra' => [
            'title' => 'Chính sách đổi trả',
            'content' => 'Khi cần đổi trả, khách hàng nên cung cấp mã đơn hàng và tình trạng sản phẩm để được hỗ trợ chính xác.',
        ],
        'thanh_toan' => [
            'title' => 'Phương thức thanh toán',
            'content' => 'Chatbox có thể tham chiếu thông tin thanh toán từ đơn hàng nếu dữ liệu đã có trong hệ thống.',
        ],
        'van_chuyen' => [
            'title' => 'Vận chuyển',
            'content' => 'Chatbox có thể phản hồi tình trạng đơn hàng và địa chỉ nhận hàng dựa trên dữ liệu đơn của chính khách hàng.',
        ],
    ],
];
