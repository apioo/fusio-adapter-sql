<?php

return [
    'app_news' => [
        ['id' => 1, 'title' => 'foo', 'price' => 39.99, 'content' => 'bar', 'image' => str_repeat("\0", 16), 'posted' => '13:37:00', 'date' => '2015-02-27 19:59:15'],
        ['id' => 2, 'title' => 'baz', 'price' => null, 'content' => null, 'image' => null, 'posted' => null, 'date' => null],
        ['id' => 3, 'title' => 'bar', 'price' => 29.99, 'content' => 'foo', 'image' => str_repeat("\0", 16), 'posted' => '13:37:00', 'date' => '2015-02-27 19:59:15'],
    ],
    'app_news_uuid' => [
        ['id' => 'b45412cb-8c50-44b8-889f-f0e78e8296ad', 'title' => 'foo', 'price' => 39.99, 'content' => 'bar', 'image' => str_repeat("\0", 16), 'posted' => '13:37:00', 'date' => '2015-02-27 19:59:15'],
        ['id' => 'a50c1ee4-3e79-493e-962f-deced0c3d797', 'title' => 'baz', 'price' => null, 'content' => null, 'image' => null, 'posted' => null, 'date' => null],
        ['id' => '0aeb1959-4552-4a4a-968e-61bf9d6a9ea5', 'title' => 'bar', 'price' => 29.99, 'content' => 'foo', 'image' => str_repeat("\0", 16), 'posted' => '13:37:00', 'date' => '2015-02-27 19:59:15'],
    ]
];
