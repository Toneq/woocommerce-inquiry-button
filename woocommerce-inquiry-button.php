<?php
/*
Plugin Name: Woocommerce Inquiry Button
Description: Zastępuje przycisk "Dodaj do koszyka" na "Zapytaj o produkt" gdy stan produktu jest zerowy. Dodana jest możliwość wysyłki na różne emaile dopisane do danej kategorii
Version: 1.0
Author: Tobiasz Tonn
*/

add_filter('woocommerce_loop_add_to_cart_link', 'inquiry_button', 10, 2);
function inquiry_button($button, $product) {
    if (!$product->is_in_stock() || !$product->get_price()) {
        $inquiry_url = site_url('/zapytaj-o-produkt/') . '?product_id=' . $product->get_id();
        $button = '<a href="' . esc_url($inquiry_url) . '" class="button inquiry-button wp-element-button product_type_simple">' . __('ZAPYTAJ O PRODUKT', 'woocommerce') . '</a>';
    }
    return $button;
}

add_shortcode('inquiry_form', 'inquiry_form_shortcode');
function inquiry_form_shortcode($atts) {
    $atts = shortcode_atts( array(
        'product_id' => $_GET['product_id'],
    ), $atts, 'inquiry_form' );

    $product = wc_get_product($atts['product_id']);
    $category = $product->get_category_ids()[0];
    $category_email = '';
    // $brand_email = '';
    $productUrl = get_permalink($atts['product_id']);

    switch ($category) {
        //W tym momencie jest na sztywno, ale zawsze jest możliwośc pobrania z MySQL maila, który jest dopisany do danej kategorii - tak samo jest możliwość zrobienia pętli dla większej ilości kategorii
        case 1: // Kategoria 1
            $category_email = 'opiekun1@b.center';
            break;
        case 2: // Kategoria 2
            $category_email = 'opiekun2@b.center';
            break;
        // Dodanie większej ilości kategorii lub marek
        default: // Podstawowy mail jeśli nie będzie przypisany żaden mail do kategorii
            $category_email = 'opiekun-ogolny@outlook.com';
            break;
    }

    // W ten sam sposób można dodać z markami
    // switch ($brand) {
    //     case 1:
    //         $brand_email = 'opiekun1@b.center';
    //         break;
    //     case 2:
    //         $brand_email = 'opiekun2@b.center';
    //         break;
    //     // Dodanie większej ilości kategorii lub marek
    //     default:
    //         $brand_email = 'opiekun-ogolny@outlook.com';
    //         break;
    // }


    ob_start();
?>
    <form class="inquiry-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="inquiry_form">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($atts['product_id']); ?>">
        <input type="hidden" name="category_email" value="<?php echo esc_attr($category_email); ?>">
        <!-- <input type="hidden" name="brand_email" value="<?php echo esc_attr($brand_email); ?>"> -->
        <div class="form-group">
            <label for="name"><?php _e('Firstname', 'woocommerce'); ?></label>
            <input type="text" name="name" id="name" required>
        </div>
        <div class="form-group">
            <label for="phone"><?php _e('Phone', 'woocommerce'); ?></label>
            <input type="tel" name="phone" id="phone" pattern="[0-9]{9}" required>
        </div>
        <div class="form-group">
            <label for="client_email"><?php _e('Email', 'woocommerce'); ?></label>
            <input type="email" name="client_email" id="client_email" required>
        </div>
        <div class="form-group">
            <label for="title"><?php _e('Title', 'woocommerce'); ?></label>
            <input type="title" name="title" id="title" required>
        </div>
        <div class="form-group">
            <label for="message"><?php _e('Message', 'woocommerce'); ?></label>
            <textarea name="message" id="message" required></textarea>
        </div>
        <input type="submit" value="<?php _e('Send', 'woocommerce'); ?>">
    </form>
<?php
    return ob_get_clean();
}

add_action('admin_post_inquiry_form', 'inquiry_form_submit');
add_action('admin_post_nopriv_inquiry_form', 'inquiry_form_submit');
function inquiry_form_submit() {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $productUrl = get_permalink($product_id);
    $product = wc_get_product($product_id);
    // $brand_email = isset($_POST['brand_email']) ? sanitize_email($_POST['brand_email']) : '';
    $category_email = isset($_POST['category_email']) ? sanitize_email($_POST['category_email']) : '';
    $client_email = isset($_POST['client_email']) ? sanitize_email($_POST['client_email']) : '';
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    $title = isset($_POST['title']) ? sanitize_title($_POST['title']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';

    // Sprawdzanie czy produkt istnieje i jest opublikowany
    if (!$product || !$product->is_visible()) {
        wp_redirect(site_url());
        exit;
    }

    // Sprawdzanie czy email klienta jest prawidłowy
    if (!is_email($client_email)) {
        wp_redirect(get_permalink($product_id));
        exit;
    }

    // Sprawdzanie czy email opiekuna jest prawidłowy
    if (!is_email($category_email)) {
        wp_redirect(get_permalink($product_id));
        exit;
    }

    // Sprawdzanie czy email opiekuna jest prawidłowy
    // if (!is_email($brand_email)) {
    //     wp_redirect(get_permalink($product_id));
    //     exit;
    // }

    // Wysyłanie emaila z zapytaniem w tym wypadku trzeba byłoby zrobić ify które wyślą email do odpowiednich opiekunów
    $to = $category_email;
    $subject = sprintf(__('%s - Produkt: %s (%s)', 'woocommerce'), $title, $product->get_name(), $productUrl);
    $headers[] = 'From: ' . $name . ' <' . $client_email . '>';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $message = sprintf(__('Imię: %s<br>Email: %s<br>Message: %s', 'woocommerce'), $name, $client_email, $message);

    wp_mail($to, $subject, $message, $headers);

    // Powrót do strony z produktem o które było pytanie
    wp_redirect(get_permalink($product_id));
    exit;
}