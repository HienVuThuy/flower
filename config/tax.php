<?php

return [

    /*
     * | THUẾ GIÁ TRỊ GIA TĂNG (VAT)
     * | ⚠️ MỨC THUẾ SUẤT NÀY LÀ GIÁ TRỊ MẶC ĐỊNH KỸ THUẬT, KHÔNG PHẢI
     */

    'default_rate' => (float) env('TAX_DEFAULT_RATE', 0.08),

    'enabled' => (bool) env('TAX_ENABLED', true),

    'scale' => 2,

];
