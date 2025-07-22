<?php
/**
 * Plugin Name: Custom Login Blocker
 * Description: Restricts access to site content and navigation unless the user is logged in. Includes custom login and registration forms via shortcodes.
 * Version: 1.1
 * Author: Paul
 */

defined('ABSPATH') || exit;

// Front-end login form
add_shortcode('custom_login_form', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log'], $_POST['pwd'])) {
        $username = sanitize_user($_POST['log']);
        $password = $_POST['pwd'];

        if ($username === 'banneduser') {
            wp_die('Access denied.');
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            wp_redirect(home_url('/login?login=failed'));
            exit;
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);

        wp_redirect(home_url('/dashboard'));
        exit;
    }

    ob_start();
    ?>
    <?php if (isset($_GET['login']) && $_GET['login'] === 'failed'): ?>
        <p style="color: red;">Login failed. Please try again.</p>
    <?php endif; ?>
    <form method="post" action="">
        <p><label>Username<br><input type="text" name="log" required></label></p>
        <p><label>Password<br><input type="password" name="pwd" required></label></p>
        <p><input type="submit" value="Log In"></p>
    </form>
    <?php
    return ob_get_clean();
});

// Front-end registration form
add_shortcode('custom_register_form', function() {
    ob_start();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_user'], $_POST['new_email'], $_POST['new_pass'])) {
        $username = sanitize_user($_POST['new_user']);
        $email = sanitize_email($_POST['new_email']);
        $password = $_POST['new_pass'];


        if (username_exists($username) || email_exists($email)) {
            echo '<p style="color:red;">That username or email is already registered.</p>';
        } else {
            $password = wp_generate_password();
            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                echo '<p style="color:red;">Error: ' . esc_html($user_id->get_error_message()) . '</p>';
            } else {
                // Auto-login the new user
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                do_action('wp_login', $username, get_user_by('id', $user_id));

                // Redirect to home
                wp_redirect(home_url('/'));
                exit;
            }
        }
    }
    ?>
    <form method="post" action="">
        <p><label>Username<br><input type="text" name="new_user" required></label></p>
        <p><label>Email<br><input type="email" name="new_email" required></label></p>
        <p><label>Password<br><input type="password" name="new_pass" required></label></p>
        <p><input type="submit" value="Register"></p>
    </form>

    <?php
    return ob_get_clean();
});

// redirect user to home after login
add_filter('login_redirect', function($redirect_to, $request, $user) {
    if (is_wp_error($user)) return $redirect_to;
    return home_url('/');
}, 10, 3);

// Hide navigation for non-logged-in users
add_action('wp_enqueue_scripts', function() {
    if (!is_user_logged_in()) {
        wp_add_inline_style('wp-block-library', '
            .wp-block-buttons button {
                pointer-events: none;
                opacity: 0.5;
                cursor: not-allowed;
            }
            .wp-site-blocks,
            .wp-block-group.alignwide,
            .wp-block-group.alignfull,
            .wp-block-post-content {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                max-width: 100% !important;
            }
        ');
    }
});

add_action('wp_footer', function () {
    if (is_user_logged_in()) return;

    if (is_page('login') || is_page('register')) return;

    ?>
    <style>
        #auth-modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.85);
            color: white;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            padding: 2rem;
        }
        #auth-modal-overlay h2 {
            margin-bottom: 1rem;
        }
        #auth-modal-overlay .auth-buttons {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
        }
        #auth-modal-overlay .auth-buttons a {
            background: #ffffff;
            color: #000;
            padding: 0.75rem 1.25rem;
            border-radius: 15px;
            text-decoration: none;
            font-weight: bold;
            font-family: manrope, sans-serif;
        }
        #auth-modal-overlay .auth-buttons a:hover {
            background: #e0e0e0;
        }
    </style>

    <div id="auth-modal-overlay">
        <h2>Please register or login to view site contents</h2>
        <div class="auth-buttons">
            <a href="<?= esc_url(home_url('/register')) ?>">Register New Account</a>
            <a href="<?= esc_url(home_url('/login')) ?>">Login</a>
        </div>
    </div>
    <?php
});
