<?php
if (!defined('ABSPATH')) exit;

add_shortcode('agent_dashboard_v4', function () {
    ob_start();

    $user = wp_get_current_user();

    // Agent listings source chain: custom tables first, then Houzez property posts.
    $listings = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user->ID) : [];
    if (empty($listings) && function_exists('ms_get_properties_by_owner')) {
        // Fallback: sometimes agent_id is not mapped, but owner_id is.
        $listings = ms_get_properties_by_owner($user->ID);
    }

    $houzez_submit_link = '';
    $houzez_messages_link = '';
    $houzez_properties_link = '';
    if (function_exists('houzez_get_template_link_2')) {
        $houzez_submit_link = houzez_get_template_link_2('template/user_dashboard_submit.php');
        $houzez_messages_link = houzez_get_template_link_2('template/user_dashboard_messages.php');
        $houzez_properties_link = houzez_get_template_link_2('template/user_dashboard_properties.php');
    } elseif (function_exists('houzez_get_template_link')) {
        $houzez_submit_link = houzez_get_template_link('template/user_dashboard_submit.php');
        $houzez_messages_link = houzez_get_template_link('template/user_dashboard_messages.php');
        $houzez_properties_link = houzez_get_template_link('template/user_dashboard_properties.php');
    }

    // Ensure the submit link never resolves to a broken '#': fallback chain
    $houzez_submit_final = $houzez_submit_link;
    if (empty($houzez_submit_final)) {
        $submit_page = get_page_by_path('submit-property');
        if ($submit_page && !empty($submit_page->ID)) {
            $houzez_submit_final = get_permalink($submit_page->ID);
        } else {
            $houzez_submit_final = home_url('/submit-property/');
        }
    }

    if (empty($listings)) {
        $author_listings = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'author' => $user->ID,
            'fields' => 'all',
        ));

        $agent_meta_listings = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'fave_agents',
                    'value' => '"' . $user->ID . '"',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'fave_property_agency',
                    'value' => '"' . $user->ID . '"',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'all',
        ));

        if (!empty($author_listings) || !empty($agent_meta_listings)) {
            $listing_ids = array();
            $merged = array();
            foreach (array_merge($author_listings, $agent_meta_listings) as $post) {
                if (!in_array($post->ID, $listing_ids, true)) {
                    $listing_ids[] = $post->ID;
                    $merged[] = $post;
                }
            }
            $listings = $merged;
        }
    }

    $listings_count = is_array($listings) ? count($listings) : 0;
    $agent_maintenance_count = function_exists('ms_get_agent_open_maintenance_requests_count') ? ms_get_agent_open_maintenance_requests_count($user->ID) : 0;
    $agent_maintenance_requests = function_exists('ms_get_agent_maintenance_requests') ? ms_get_agent_maintenance_requests($user->ID, 20) : array();
    $agent_invoices = function_exists('ms_get_agent_invoices') ? ms_get_agent_invoices($user->ID, 20, 'listing_fee') : array();
    $agent_due_total = function_exists('ms_get_agent_invoice_total_due') ? ms_get_agent_invoice_total_due($user->ID, 'listing_fee') : 0;

    ?>

    <div class="ms-dashboard">

        <?php
        $unread_notifications_count = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user->ID) : 0;
        $notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user->ID, 20) : array();

        $ms_dashboard_menu_items = array(
            array('label' => 'إضافة عقار', 'data_tab' => 'add-property', 'icon' => '✍️'),
            array('label' => 'العقارات', 'data_tab' => 'listings', 'icon' => '🏠', 'badge' => intval($listings_count)),
            array('label' => 'الاشتراكات', 'data_tab' => 'subscriptions', 'icon' => '📦'),
            array('label' => 'الصيانة', 'data_tab' => 'maintenance', 'icon' => '🛠️', 'badge' => intval($agent_maintenance_count)),
            array('label' => 'الفواتير', 'data_tab' => 'invoices', 'icon' => '📄', 'badge' => intval(count($agent_invoices))),
            array('label' => 'المناقشات', 'data_tab' => 'discussions', 'icon' => '💬'),
            array('label' => 'الإحصائيات', 'data_tab' => 'analytics', 'icon' => '📊'),
            array('label' => 'الإشعارات', 'data_tab' => 'notifications', 'icon' => '🔔', 'badge' => $unread_notifications_count),
            array('href' => wp_logout_url(), 'label' => 'تسجيل الخروج', 'external' => true, 'icon' => '🚪'),
        );
        ms_load_dashboard_sidebar($ms_dashboard_menu_items);
        ?>
        <main class="ms-content">

            <div class="ms-tab-content" id="add-property">
                <div class="ms-card">
                    <h3>إضافة عقار</h3>
                    <?php
                    $embedded_houzez_submit = '';
                    $houzez_submit_template = locate_template('template/user_dashboard_submit.php');
                    if (empty($houzez_submit_template)) {
                        $possible = get_stylesheet_directory() . '/template/user_dashboard_submit.php';
                        if (file_exists($possible)) {
                            $houzez_submit_template = $possible;
                        }
                    }
                    if (empty($houzez_submit_template)) {
                        $possible = get_template_directory() . '/template/user_dashboard_submit.php';
                        if (file_exists($possible)) {
                            $houzez_submit_template = $possible;
                        }
                    }

                    if ($houzez_submit_template && file_exists($houzez_submit_template)) {
                        global $mostaager_agent_add_property_dashboard;
                        $previous_mostaager_agent_add_property_dashboard = ! empty( $GLOBALS['mostaager_agent_add_property_dashboard'] );
                        $GLOBALS['mostaager_agent_add_property_dashboard'] = true;
                        ob_start();
                        include $houzez_submit_template;
                        $embedded_houzez_submit = ob_get_clean();
                        $GLOBALS['mostaager_agent_add_property_dashboard'] = $previous_mostaager_agent_add_property_dashboard;

                        if (!empty($embedded_houzez_submit)) {
                            if (preg_match('/<div class="dashboard-right">.*?<\/div>\s*<!-- dashboard-right -->/si', $embedded_houzez_submit, $matches)) {
                                $embedded_houzez_submit = $matches[0];
                            } elseif (preg_match('/<div class="dashboard-content">.*?<\/div>\s*<!-- dashboard-content -->/si', $embedded_houzez_submit, $matches)) {
                                $embedded_houzez_submit = $matches[0];
                            }
                        }
                    }

                    if (!empty($embedded_houzez_submit)) {
                        echo $embedded_houzez_submit;
                    } else {
                        echo '<p>الصفحة المطلوبة غير متوفرة داخل النظام. يمكنك الانتقال إلى صفحة الإضافة الخارجية:</p>';
                        echo '<a href="' . esc_url($houzez_submit_final) . '" class="button" style="display:inline-block;margin-top:14px;padding:10px 16px;background:#2563eb;color:#fff;text-decoration:none;border-radius:4px;">فتح صفحة إضافة عقار</a>';
                    }
                    ?>
                </div>
            </div>

            <div class="ms-tab-content active" id="listings">
                <div class="ms-grid">
                    <div class="ms-card"><h3>العقارات المنشورة</h3><div id="ms-listings-count" class="ms-number"><?php echo intval($listings_count); ?></div></div>
                    <div class="ms-card"><h3>العقارات المنتهية</h3><div id="ms-expired-count" class="ms-number">0</div></div>
<div class="ms-card"><h3>الرسوم الشهرية</h3><div id="ms-monthly-fees" class="ms-number">ج.م 0</div></div>
                    <div class="ms-card"><h3>الدفعات</h3><div id="ms-payments-count" class="ms-number">0</div></div>
                </div>

                <div class="ms-card" style="margin-top:20px">
                    <?php
                    // Prepare Houzez-style properties query for agent
                    global $properties_query, $delete_properties_nonce;
                    $delete_properties_nonce = wp_create_nonce('delete_properties_nonce');

                    $paged = get_query_var('paged') ?: get_query_var('page') ?: 1;

                    $default_statuses = array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future');

                    $search_keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '';
                    $post_status_filter = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : '';
                    $property_id_filter = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
                    $min_price = isset($_GET['min-price']) ? floatval($_GET['min-price']) : 0;
                    $max_price = isset($_GET['max-price']) ? floatval($_GET['max-price']) : 0;
                    $type_filter = isset($_GET['type']) ? $_GET['type'] : '';
                    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
                    $featured_filter = isset($_GET['featured']) ? sanitize_text_field($_GET['featured']) : '';

                    $tax_query = array();
                    $meta_query = array('relation' => 'AND');

                    if (!empty($type_filter)) {
                        $type_terms = is_array($type_filter) ? array_map('sanitize_text_field', $type_filter) : array(sanitize_text_field($type_filter));
                        $tax_query[] = array(
                            'taxonomy' => 'property_type',
                            'field' => 'slug',
                            'terms' => $type_terms,
                            'operator' => 'IN',
                        );
                    }

                    if (!empty($status_filter)) {
                        $status_terms = is_array($status_filter) ? array_map('sanitize_text_field', $status_filter) : array(sanitize_text_field($status_filter));
                        $tax_query[] = array(
                            'taxonomy' => 'property_status',
                            'field' => 'slug',
                            'terms' => $status_terms,
                            'operator' => 'IN',
                        );
                    }

                    if ($featured_filter !== '') {
                        $meta_query[] = array(
                            'key' => 'fave_featured',
                            'value' => sanitize_text_field($featured_filter),
                            'compare' => '=',
                        );
                    }

                    if ($min_price || $max_price) {
                        $price_query = array(
                            'key' => 'fave_property_price',
                            'type' => 'NUMERIC',
                        );
                        if ($min_price && $max_price) {
                            $price_query['value'] = array($min_price, $max_price);
                            $price_query['compare'] = 'BETWEEN';
                        } elseif ($min_price) {
                            $price_query['value'] = $min_price;
                            $price_query['compare'] = '>=';
                        } else {
                            $price_query['value'] = $max_price;
                            $price_query['compare'] = '<=';
                        }
                        $meta_query[] = $price_query;
                    }

                    if ($property_id_filter) {
                        $args = array(
                            'post_type' => 'property',
                            'paged' => $paged,
                            'posts_per_page' => 10,
                            'suppress_filters' => false,
                            'p' => $property_id_filter,
                            'post_status' => $default_statuses,
                        );
                    } else {
                        if ($post_status_filter === 'all' || $post_status_filter === '' || $post_status_filter === 'mine') {
                            $resolved_statuses = $default_statuses;
                        } elseif ($post_status_filter === 'approved' || $post_status_filter === 'publish') {
                            $resolved_statuses = array('publish');
                        } elseif ($post_status_filter === 'sold') {
                            $resolved_statuses = array('houzez_sold');
                        } else {
                            $resolved_statuses = array($post_status_filter);
                        }

                        $resolved_property_ids = array();
                        if (!empty($listings)) {
                            $raw_ids = array();
                            foreach ($listings as $p_item) {
                                $item_id = is_object($p_item) && isset($p_item->ID) ? intval($p_item->ID) : (isset($p_item->id) ? intval($p_item->id) : 0);
                                if ($item_id) {
                                    $raw_ids[] = $item_id;
                                    $post = get_post($item_id);
                                    if ($post && $post->post_type === 'property') {
                                        $resolved_property_ids[] = $item_id;
                                        continue;
                                    }
                                }

                                $building_id = intval($p_item->building_id ?? 0);
                                if (!$building_id && isset($p_item->unit) && is_object($p_item->unit)) {
                                    $building_id = intval($p_item->unit->building_id ?? 0);
                                }
                                if ($building_id && function_exists('ms_get_properties_by_building')) {
                                    $props_by_building = ms_get_properties_by_building($building_id);
                                    foreach ((array)$props_by_building as $property_row) {
                                        $property_id = intval($property_row->ID ?? $property_row->id ?? 0);
                                        if ($property_id && !in_array($property_id, $resolved_property_ids, true)) {
                                            $resolved_property_ids[] = $property_id;
                                        }
                                    }
                                }

                                if (!$item_id && !empty($p_item->property_id)) {
                                    $property_id = intval($p_item->property_id);
                                    $property_post = get_post($property_id);
                                    if ($property_post && $property_post->post_type === 'property') {
                                        $resolved_property_ids[] = $property_id;
                                    }
                                }
                            }
                            $resolved_property_ids = array_values(array_unique($resolved_property_ids));

                            $args = array(
                                'post_type' => 'property',
                                'paged' => $paged,
                                'posts_per_page' => 10,
                                'suppress_filters' => false,
                                'post_status' => $resolved_statuses,
                            );
                            if (!empty($resolved_property_ids)) {
                                $args['post__in'] = $resolved_property_ids;
                            } else {
                                $args['post__in'] = array_values(array_unique($raw_ids));
                            }
                        } else {
                            $args = array(
                                'post_type' => 'property',
                                'paged' => $paged,
                                'posts_per_page' => 10,
                                'suppress_filters' => false,
                                'author' => $user->ID,
                                'post_status' => $resolved_statuses,
                            );
                        }
                    }

                    if (!empty($search_keyword)) {
                        $args['s'] = $search_keyword;
                    }
                    if (!empty($tax_query)) {
                        $args['tax_query'] = $tax_query;
                    }
                    if (count($meta_query) > 1) {
                        $args['meta_query'] = $meta_query;
                    }

                    $properties_query = new WP_Query($args);
                    ?>

                    <?php if ($properties_query->have_posts()): ?>
                        <?php
                        $current_page_url = get_permalink();
                        $current_status = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : 'all';
                        $tabs = array(
                            'all' => 'الجميع',
                            'mine' => 'عقاراتي',
                            'publish' => 'الموافق عليها و المنشورة',
                            'draft' => 'المسودة',
                        );
                        $tab_counts = array(
                            'all' => function_exists('houzez_user_posts_count') ? houzez_user_posts_count('any') : count($listings),
                            'mine' => function_exists('houzez_user_posts_count') ? houzez_user_posts_count('any', true) : count($listings),
                            'publish' => function_exists('houzez_user_posts_count') ? houzez_user_posts_count('publish') : 0,
                            'draft' => function_exists('houzez_user_posts_count') ? houzez_user_posts_count('draft') : 0,
                        );
                        ?>

                        <div class="houzez-properties-tabs-js">
                            <div class="property-nav-tabs" style="position:relative; z-index:9999; touch-action:manipulation;">
                                <ul>
                                    <?php foreach ($tabs as $status => $label): ?>
                                        <?php
                                        $tab_params = array_merge($_GET, array('post_status' => $status, 'paged' => 1));
                                        $tab_url = add_query_arg($tab_params, $current_page_url);
                                        ?>
                                        <li>
                                            <button type="button" class="property-tab-button <?php echo $current_status === $status ? 'active' : ''; ?>" data-url="<?php echo esc_url($tab_url); ?>" aria-pressed="<?php echo $current_status === $status ? 'true' : 'false'; ?>" onclick="event.preventDefault(); event.stopImmediatePropagation(); window.location.assign(this.dataset.url); return false;" onpointerdown="event.preventDefault(); event.stopImmediatePropagation(); window.location.assign(this.dataset.url); return false;" onmousedown="event.preventDefault(); event.stopImmediatePropagation(); window.location.assign(this.dataset.url); return false;" ontouchstart="event.preventDefault(); event.stopImmediatePropagation(); window.location.assign(this.dataset.url); return false;">
                                                <?php echo esc_html($label); ?> <span>(<?php echo intval($tab_counts[$status]); ?>)</span>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <script>
                                (function(){
                                    console.log('Mostaager inline tab loader active');
                                    var tabNav = document.querySelector('.property-nav-tabs');
                                    if (!tabNav) {
                                        console.log('Mostaager inline tab loader: no tabNav found');
                                        return;
                                    }
                                    tabNav.style.pointerEvents = 'auto';
                                    tabNav.style.zIndex = '999999';
                                    tabNav.style.position = 'relative';
                                    var buttons = tabNav.querySelectorAll('button.property-tab-button');
                                    console.log('Mostaager tab buttons found', buttons.length);
                                    buttons.forEach(function(button){
                                        button.addEventListener('click', function(e){
                                            console.log('Mostaager tab click', this.dataset.url, e.target);
                                            e.preventDefault();
                                            e.stopImmediatePropagation();
                                            var url = this.dataset.url;
                                            if (url) {
                                                window.location.assign(url);
                                            }
                                        }, true);
                                    });
                                    var links = tabNav.querySelectorAll('a[href]');
                                    console.log('Mostaager tab links found', links.length);
                                    links.forEach(function(link){
                                        link.addEventListener('click', function(e){
                                            console.log('Mostaager tab anchor click', this.href, e.target);
                                            if (this.getAttribute('href') === '#' || !this.href) return;
                                            e.preventDefault();
                                            e.stopImmediatePropagation();
                                            window.location.assign(this.href);
                                        }, true);
                                    });

                                    document.addEventListener('pointerdown', function(e){
                                        if (e.clientX == null || e.clientY == null) return;
                                        var tabRect = tabNav.getBoundingClientRect();
                                        if (e.clientX >= tabRect.left && e.clientX <= tabRect.right && e.clientY >= tabRect.top && e.clientY <= tabRect.bottom) {
                                            var el = document.elementFromPoint(e.clientX, e.clientY);
                                            console.log('Mostaager pointerdown in tab area', e.target, 'elementFromPoint', el, 'coords', e.clientX, e.clientY);
                                            if (el && !el.closest('button.property-tab-button') && !el.closest('a[href]')) {
                                                console.log('Mostaager covering element', el, 'closest button', el.closest('button.property-tab-button'), 'closest link', el.closest('a[href]'));
                                            }
                                        }
                                    }, true);
                                    document.addEventListener('mousedown', function(e){
                                        if (e.clientX == null || e.clientY == null) return;
                                        var tabRect = tabNav.getBoundingClientRect();
                                        if (e.clientX >= tabRect.left && e.clientX <= tabRect.right && e.clientY >= tabRect.top && e.clientY <= tabRect.bottom) {
                                            console.log('Mostaager mousedown in tab area', e.target, 'coords', e.clientX, e.clientY);
                                        }
                                    }, true);
                                    document.addEventListener('click', function(e){
                                        if (e.clientX == null || e.clientY == null) return;
                                        var tabRect = tabNav.getBoundingClientRect();
                                        if (e.clientX >= tabRect.left && e.clientX <= tabRect.right && e.clientY >= tabRect.top && e.clientY <= tabRect.bottom) {
                                            console.log('Mostaager click in tab area', e.target, 'coords', e.clientX, e.clientY);
                                        }
                                    }, true);
                                })();
                            </script>
                            <div class="houzez-tab-content">
                                <div class="houzez-data-content">
                                    <?php
                                    global $dashboard_properties;
                                    $dashboard_properties = $current_page_url;
                                    get_template_part('template-parts/dashboard/property/filters');
                                    ?>

                                    <div class="houzez-data-table">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle m-0">
                                                <thead>
                                                    <tr>
                                                        <th data-label="Select"></th>
                                                        <th data-label="Thumbnail">Thumbnail</th>
                                                        <th data-label="Title">Title</th>
                                                        <th data-label="Status">Status</th>
                                                        <th data-label="Price">Price</th>
                                                        <th data-label="Actions">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($properties_query->have_posts()): $properties_query->the_post(); ?>
                                                        <?php
                                                        $ms_dashboard_context = 'agent';
                                                        include MOSTAAGER_ENTERPRISE_PATH . 'templates/partials/property-row.php';
                                                        ?>
                                                    <?php endwhile; wp_reset_postdata(); ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <?php get_template_part('template-parts/dashboard/property/pagination'); ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>لا توجد عقارات مرتبطة بالوسيط حالياً.</p>
                        <p style="color:#666;margin-top:8px">قد تكون علاقة agent_id غير مخزنة في ms_units، لذلك تم استخدام owner_id كحل بديل أو البحث في عقارات Houzez.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php
    $agent_subscription = function_exists('ms_get_agent_subscription') ? ms_get_agent_subscription($user->ID) : null;
    $subscription_status = function_exists('ms_get_agent_subscription_status') ? ms_get_agent_subscription_status($user->ID) : array('monthly_fee' => 0, 'status' => 'غير معروفة', 'due' => 0);
    $active_package_name = $agent_subscription && !empty($agent_subscription->package_name) ? $agent_subscription->package_name : ($agent_subscription && !empty($agent_subscription->package) ? $agent_subscription->package : 'لا يوجد باقة مفعلة');
    $is_subscribed = $agent_subscription ? true : false;
    ?>
            <div class="ms-tab-content" id="subscriptions">
                <div class="ms-card">
                    <h3>حالة الاشتراكات</h3>
                    <?php if ($is_subscribed): ?>
                        <p style="margin-bottom:16px;font-weight:700;">أنت مشترك حالياً في باقة <span style="color:#2563eb;"><?php echo esc_html($active_package_name); ?></span>.</p>
                    <?php else: ?>
                        <p style="margin-bottom:16px;color:#555;">لم يتم تفعيل أي باقة حتى الآن. اختر الباقة المناسبة وابدأ في إضافة العقارات.</p>
                    <?php endif; ?>
                    <div class="ms-subscription-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-top:20px;">
                        <?php
                        $plans = array(
                            array(
                                'name' => 'الباقة الذهبية',
                                'amount' => 100.00,
                                'display_price' => '100$',
                                'capacity' => '50 شقة',
                                'description' => 'أفضل باقة لإضافة عدد كبير من العقارات مع دعم مخصص.',
                                'package_key' => 'gold',
                            ),
                            array(
                                'name' => 'الباقة الفضية',
                                'amount' => 50.00,
                                'display_price' => '50$',
                                'capacity' => '25 شقة',
                                'description' => 'باقة متوازنة تناسب معظم الوسطاء الذين يريدون نمو مستمر.',
                                'package_key' => 'silver',
                            ),
                            array(
                                'name' => 'الباقة البرونزية',
                                'amount' => 25.00,
                                'display_price' => '25$',
                                'capacity' => '10 شقة',
                                'description' => 'باقة تمهيدية مناسبة للوسطاء الجدد أو الميزانية المحدودة.',
                                'package_key' => 'bronze',
                            ),
                        );
                        foreach ($plans as $plan):
                            $is_active = $is_subscribed && stripos($active_package_name, $plan['name']) !== false;
                        ?>
                            <div class="ms-subscription-card" style="border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 10px 25px rgba(0,0,0,0.05);background:#fff;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px;">
                                    <div>
                                        <h4 style="margin:0 0 8px;font-size:1.1rem;"><?php echo esc_html($plan['name']); ?></h4>
                                        <p style="margin:0;color:#475569;line-height:1.5;"><?php echo esc_html($plan['description']); ?></p>
                                    </div>
                                    <?php if ($is_active): ?>
                                        <span style="padding:6px 12px;background:#dbeafe;color:#1d4ed8;border-radius:999px;font-size:0.85rem;font-weight:700;">مفعلة</span>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-bottom:18px;">
                                    <div style="font-size:2rem;font-weight:800;color:#111827;"><?php echo esc_html($plan['display_price']); ?></div>
                                    <div style="color:#475569;">شهرياً</div>
                                </div>
                                <div style="margin-bottom:20px;color:#334155;font-weight:700;">يحق للوسيط فيها إضافة <?php echo esc_html($plan['capacity']); ?></div>
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <?php if ($is_active): ?>
                                        <span style="color:#16a34a;font-weight:700;">أنت مشترك بهذه الباقة</span>
                                    <?php else: ?>
                                        <a href="#" class="btn btn-primary ms-subscription-purchase-btn" style="background:#2563eb;border-color:#2563eb;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;" data-plan-key="<?php echo esc_attr($plan['package_key']); ?>" data-plan-name="<?php echo esc_attr($plan['name']); ?>" data-plan-price="<?php echo esc_attr($plan['amount']); ?>">اشترِ الآن</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="ms-tab-content" id="maintenance">
                <div class="ms-card">
                    <h3>طلبات الصيانة</h3>
                    <p style="color:#666;margin-top:8px">عرض طلبات الصيانة المرتبطة بالمباني الخاصة بالعقارات التي يديرها الوسيط.</p>
                    <?php if (!empty($agent_maintenance_requests)): ?>
                        <div class="table-responsive" style="margin-top:14px;">
                            <table class="table table-hover align-middle m-0">
                                <thead>
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th>العنوان</th>
                                        <th>الحالة</th>
                                        <th>الأولوية</th>
                                        <th>التكلفة</th>
                                        <th>تاريخ الإنشاء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agent_maintenance_requests as $request): ?>
                                        <tr>
                                            <td><?php echo esc_html($request->id ?? 'N/A'); ?></td>
                                            <td><?php echo esc_html($request->title ?? 'بدون عنوان'); ?></td>
                                            <td><?php echo esc_html($request->status ?? 'غير معروف'); ?></td>
                                            <td><?php echo esc_html($request->priority ?? 'متوسط'); ?></td>
                                            <td><?php echo isset($request->cost) ? esc_html(number_format(floatval($request->cost), 2)) : '0.00'; ?></td>
                                            <td><?php echo esc_html($request->created_at ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:#666;margin-top:12px">لم يتم العثور على طلبات صيانة مرتبطة بهذه العقارات.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="invoices">
                <div class="ms-card">
                    <h3>فواتير رسوم الوسيط</h3>
                    <p style="color:#666;margin-top:8px">عرض الفواتير المولدة لفئة الرسوم المرتبطة بالوسيط.</p>
                    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
                        <div class="ms-card" style="flex:1;min-width:200px;">
                            <h4>إجمالي المستحق</h4>
                            <div class="ms-number">ج.م <?php echo esc_html(number_format(floatval($agent_due_total), 2)); ?></div>
                        </div>
                        <div class="ms-card" style="flex:1;min-width:200px;">
                            <h4>عدد الفواتير</h4>
                            <div class="ms-number"><?php echo intval(count($agent_invoices)); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($agent_invoices)): ?>
                        <div class="table-responsive" style="margin-top:20px;">
                            <table class="table table-hover align-middle m-0">
                                <thead>
                                    <tr>
                                        <th>رقم الفاتورة</th>
                                        <th>الوصف</th>
                                        <th>الحالة</th>
                                        <th>المبلغ</th>
                                        <th>تاريخ الإنشاء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agent_invoices as $invoice): ?>
                                        <tr>
                                            <td><?php echo esc_html($invoice->id ?? ($invoice->invoice_number ?? 'N/A')); ?></td>
                                            <td><?php echo esc_html($invoice->description ?? $invoice->invoice_type ?? 'فاتورة'); ?></td>
                                            <td><?php echo esc_html($invoice->status ?? 'غير معروف'); ?></td>
                                            <td><?php echo isset($invoice->amount) ? esc_html(number_format(floatval($invoice->amount), 2)) : '0.00'; ?></td>
                                            <td><?php echo esc_html($invoice->created_at ?? $invoice->due_date ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:#666;margin-top:12px">لا توجد فواتير رسوم وسيط حالياً.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="discussions">
                <div class="ms-card">
                    <h3>المناقشات</h3>
                    <?php
                    // Resolve a building_id from the agent's properties (first matching building_id meta)
                    $discussion_building_id = 0;
                    if (!empty($listings)) {
                        $building_ids = array();
                        foreach ($listings as $p_item) {
                            // If listing is an ms_units-like object with building_id property, use it directly
                            if (is_object($p_item) && isset($p_item->building_id) && intval($p_item->building_id)) {
                                $b = intval($p_item->building_id);
                                $building_ids[] = $b;
                                continue;
                            }
                            $prop_id = is_object($p_item) && isset($p_item->ID) ? intval($p_item->ID) : (isset($p_item->id) ? intval($p_item->id) : 0);
                            if ($prop_id) {
                                $b = get_post_meta($prop_id, 'building_id', true);
                                $b = intval($b);
                                if ($b) $building_ids[] = $b;
                            }
                        }
                        $building_ids = array_values(array_unique($building_ids));
                        if (!empty($building_ids)) {
                            $discussion_building_id = $building_ids[0];
                        }
                    }
                    ?>

                    <?php if (!$discussion_building_id): ?>
                        <p style="color:#666;margin-top:8px">لم يتم العثور على معرف مبنى صالح مرتبط بخصائص الوسيط. لا يمكن تحميل مواضيع المناقشات.</p>
                    <?php else: ?>
                        <div id="agent-discussions" data-building-id="<?php echo intval($discussion_building_id); ?>">
                            
                            <div class="ms-discussions-layout" style="display:flex;gap:12px;align-items:flex-start;">
                                <div class="ms-discussions-list" style="width:36%;min-width:220px;border-right:1px solid #eee;padding-right:12px;">
                                    <h4 style="margin-top:0">المواضيع</h4>
                                    <ul class="ms-discussions-list-ul" style="list-style:none;padding:0;margin:0;max-height:420px;overflow:auto;"></ul>
                                </div>
                                <div class="ms-discussion-detail" style="flex:1;min-width:320px;">
                                    <div class="ms-discussion-empty" style="color:#666">اختر موضوعاً لعرض التفاصيل</div>
                                    <div class="ms-discussion-messages" style="margin-top:12px;max-height:360px;overflow:auto;border:1px solid #f3f4f6;padding:12px;background:#fff;"></div>

                                    <form class="ms-discussion-reply-form" style="margin-top:12px;display:none;">
                                        <textarea name="reply" rows="4" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;"></textarea>
                                        <div style="margin-top:8px;text-align:left;">
                                            <button type="submit" class="ms-discussion-reply-submit" style="padding:8px 12px;background:#2563eb;color:#fff;border:none;border-radius:4px;">إرسال الرد</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="analytics">
                <div class="ms-card"><h3>الإحصائيات</h3><p>عرض أداء العقارات والعمولات.</p></div>
            </div>

        </main>

    </div>

    <?php
    return ob_get_clean();

});


