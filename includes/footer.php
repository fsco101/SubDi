<footer class="site-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section about">
                <h3 class="footer-heading">About Us</h3>
                <p>We are dedicated to providing high-quality services and solutions to meet your needs. Our team of experts is committed to excellence and customer satisfaction.</p>
                <div class="contact">
                    <span><i class="fas fa-phone"></i> &nbsp;Contact#: 09672688658</span>
                    <span><i class="fas fa-envelope"></i> &nbsp;Email: subdisystem@gmail.com</span>
                </div>
            </div>

            <div class="footer-section links">
                <h3 class="footer-heading">Quick Links</h3>
                <ul>
                    <li></li>
                    <li><a href="/subdisystem/dashboard.php">Home</a></li>
                    <li><a href="/subdisystem/privacy-policy.php">Privacy Policy</a></li>
                    <li><a href="/subdisystem/terms.php">Terms of Service</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Subdi.Assoc. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Font Awesome -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<style>
    .site-footer {
        background-color: #26272b;
        padding: 45px 0 20px;
        font-size: 15px;
        line-height: 24px;
        color: #737373;
        width: 100%;
        margin-bottom: 0;
        bottom: 0;
        position: relative;
    }

    body {
        margin-bottom: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    main {
        flex: 1;
    }

    .site-footer .container {
        width: 100%;
        margin: 0;
        max-width: none;
        padding: 0 20px;
    }

    .footer-content {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        margin-bottom: 30px;
        width: 100%;
    }

    .footer-section {
        flex: 1;
        min-width: 250px;
        padding: 0 15px;
        margin-bottom: 20px;
    }

    .footer-heading {
        color: #ffffff;
        font-size: 18px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .footer-section.about .contact {
        margin-top: 20px;
    }

    .footer-section.about .contact span {
        display: block;
        margin-bottom: 10px;
    }

    .footer-section.links ul {
        list-style: none;
        padding-left: 0;
    }

    .footer-section.links ul li {
        margin-bottom: 10px;
    }

    .footer-section.links ul li a {
        color: #737373;
        text-decoration: none;
        transition: color 0.3s;
    }

    .footer-section.links ul li a:hover {
        color: #3366cc;
        padding-left: 5px;
    }

    .footer-section.newsletter .text-input {
        width: 100%;
        padding: 10px;
        margin-bottom: 10px;
        border: none;
        border-radius: 4px;
    }

    .btn {
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .btn-primary {
        background-color: #3366cc;
        color: white;
    }

    .social-media {
        text-align: center;
        margin-bottom: 20px;
    }

    .social-media a {
        display: inline-block;
        width: 40px;
        height: 40px;
        margin: 0 10px;
        text-align: center;
        line-height: 40px;
        border-radius: 50%;
        background-color: #33353d;
        color: white;
        transition: all 0.3s ease;
    }

    .social-media a:hover {
        background-color: #3366cc;
        transform: scale(1.1);
    }

    .footer-bottom {
        text-align: center;
        padding-top: 20px;
        border-top: 1px solid #444;
    }

    @media (max-width: 768px) {
        .footer-section {
            flex: 100%;
        }
    }
</style>
