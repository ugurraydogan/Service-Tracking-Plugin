<?php
/**
 * Plugin Name: Servis Takip Sistemi
 * Description: WordPress için Başlangıç Seviye Servis Takip Sistemi.
 * Version: 1.5
 * Author: Ugur Aydogan
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişimi engelle
}

// Veritabanını güncelle veya kur
function service_tracker_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'service_requests';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        customer_name varchar(100) NOT NULL,
        phone_number varchar(15) NOT NULL,
        device_model varchar(100) NOT NULL,
        service_description text NOT NULL,
        status varchar(50) DEFAULT 'beklemede' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'service_tracker_install');

// Yönetici Menüsü
function service_tracker_admin_menu() {
    add_menu_page(
        'Servis Talepleri',
        'Servis Talepleri',
        'manage_options',
        'service-tracker',
        'service_tracker_admin_page',
        'dashicons-list-view',
        26
    );
}
add_action('admin_menu', 'service_tracker_admin_menu');

// Yönetici Paneli İşlevleri
function service_tracker_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'service_requests';

    // Yeni talep ekleme işlemi
    if (isset($_POST['add_service_request'])) {
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $device_model = sanitize_text_field($_POST['device_model']);
        $service_description = sanitize_textarea_field($_POST['service_description']);

        $wpdb->insert(
            $table_name,
            [
                'customer_name' => $customer_name,
                'phone_number' => $phone_number,
                'device_model' => $device_model,
                'service_description' => $service_description,
                'status' => 'beklemede'
            ]
        );
        echo '<div class="updated"><p>Servis talebi başarıyla eklendi.</p></div>';
    }

    // Talep durumunu güncelleme işlemi geliştirilecek
    if (isset($_POST['update_status'])) {
        $request_id = intval($_POST['request_id']);
        $new_status = sanitize_text_field($_POST['status']);

        $wpdb->update(
            $table_name,
            ['status' => $new_status],
            ['id' => $request_id]
        );
        echo '<div class="updated"><p>Durum başarıyla güncellendi.</p></div>';
    }

    // Talep ekleme formu
    echo '<div class="wrap"><h1>Servis Talepleri</h1>';
    echo '<h2>Yeni Servis Talebi Ekle</h2>';
    echo '<form method="post">
        <table class="form-table">
            <tr><th>Müşteri Adı</th><td><input type="text" name="customer_name" required></td></tr>
            <tr><th>Telefon Numarası</th><td><input type="text" name="phone_number" required></td></tr>
            <tr><th>Cihaz Modeli</th><td><input type="text" name="device_model" required></td></tr>
            <tr><th>Arıza Açıklaması</th><td><textarea name="service_description" required></textarea></td></tr>
        </table>
        <p><input type="submit" name="add_service_request" class="button button-primary" value="Talebi Ekle"></p>
    </form>';

    // Servis taleplerini listele ve durum güncelle
    echo '<h2>Mevcut Servis Talepleri</h2>';
    $results = $wpdb->get_results("SELECT * FROM $table_name");

    if ($results) {
        echo '<table class="widefat fixed">
            <thead><tr>
                <th>ID</th>
                <th>Müşteri Adı</th>
                <th>Telefon</th>
                <th>Cihaz Modeli</th>
                <th>Açıklama</th>
                <th>Durum</th>
                <th>Oluşturulma Tarihi</th>
                <th>Durumu Güncelle</th>
                <th>Servis Fişi</th>
            </tr></thead><tbody>';
        foreach ($results as $request) {
            echo sprintf(
                '<tr>
                    <td>%d</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="request_id" value="%d">
                            <select name="status">
                                <option value="Teklif bekleyen" %s>Teklif bekleyen</option>
                                <option value="İptal edildi" %s>İptal edildi</option>
                                <option value="Teklif reddedildi" %s>Teklif reddedildi</option>
                                <option value="Onarım tamamlandı" %s>Onarım tamamlandı</option>
                                <option value="Onarım devam ediyor" %s>Onarım devam ediyor</option>
                                <option value="Beklemede" %s>Beklemede</option>
                            </select>
                            <input type="submit" name="update_status" class="button" value="Güncelle">
                        </form>
                    </td>
                    <td><a href="%s" target="_blank" class="button">Fiş Yazdır</a></td>
                </tr>',
                $request->id,
                esc_html($request->customer_name),
                esc_html($request->phone_number),
                esc_html($request->device_model),
                esc_html($request->service_description),
                esc_html($request->status),
                esc_html($request->created_at),
                $request->id,
                $request->status === 'Teklif bekleyen' ? 'selected' : '',
                $request->status === 'İptal edildi' ? 'selected' : '',
                $request->status === 'Teklif reddedildi' ? 'selected' : '',
                $request->status === 'Onarım tamamlandı' ? 'selected' : '',
                $request->status === 'Onarım devam ediyor' ? 'selected' : '',
                $request->status === 'Beklemede' ? 'selected' : '',
                admin_url('admin-ajax.php?action=generate_pdf&request_id=' . $request->id)
            );
        }
        echo '</tbody></table>';
    } else {
        echo '<p>Henüz bir servis talebi bulunmuyor.</p>';
    }
    echo '</div>';
}

// PDF Fişi oluşturabiliriz burayı geliştireceğim
function service_tracker_generate_pdf() {
    if (!isset($_GET['request_id'])) {
        return;
    }

    $request_id = intval($_GET['request_id']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'service_requests';

    $request = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $request_id");

    if (!$request) {
        wp_die('Servis talebi bulunamadı.');
    }

    // PDF içeriği
    $html = sprintf('
            <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        h1 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #0056b3;
        }
        p {
            margin: 10px 0;
            font-size: 14px;
        }
        p strong {
            display: inline-block;
            width: 150px;
            font-weight: bold;
            color: #000;
        }
        .service-ticket {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
            background-color: #f9f9f9;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
        <h1>Servis Fişi</h1>
        <p><strong>Müşteri Adı:</strong> %s</p>
        <p><strong>Telefon:</strong> %s</p>
        <p><strong>Cihaz Modeli:</strong> %s</p>
        <p><strong>Arıza:</strong> %s</p>
        <p><strong>Durum:</strong> %s</p>
        <p><strong>Oluşturulma Tarihi:</strong> %s</p>',
        esc_html($request->customer_name),
        esc_html($request->phone_number),
        esc_html($request->device_model),
        esc_html($request->service_description),
        esc_html($request->status),
        esc_html($request->created_at)
    );

    echo $html;
    exit;
}
add_action('wp_ajax_generate_pdf', 'service_tracker_generate_pdf');
