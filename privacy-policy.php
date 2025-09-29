<?php

$pageTitle = "Privacy Policy";
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    include __DIR__ . '/includes/header.php';
} else {
    include __DIR__ . '/includes/index_header.php';
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h1>Privacy Policy</h1>
            <p class="text-muted">Last updated: <?php echo date('F d, Y'); ?></p>
            <hr>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Introduction</h2>
                    <p>
                        At SubDi.Assoc, we respect your privacy and are committed to protecting your personal data.
                        This privacy policy will inform you about how we look after your personal data when you visit our website
                        and tell you about your privacy rights and how the law protects you.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>The Data We Collect About You</h2>
                    <p>
                        Personal data, or personal information, means any information about an individual from which that person can be identified.
                        It does not include data where the identity has been removed (anonymous data).
                    </p>
                    <p>We may collect, use, store and transfer different kinds of personal data about you which we have grouped together as follows:</p>
                    <ul>
                        <li><strong>Identity Data</strong> includes first name, last name, username or similar identifier.</li>
                        <li><strong>Contact Data</strong> includes billing address, delivery address, email address and telephone numbers.</li>
                        <li><strong>Technical Data</strong> includes internet protocol (IP) address, your login data, browser type and version, time zone setting and location, browser plug-in types and versions, operating system and platform, and other technology on the devices you use to access this website.</li>
                        <li><strong>Usage Data</strong> includes information about how you use our website, products and services.</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>How We Use Your Personal Data</h2>
                    <p>We will only use your personal data when the law allows us to. Most commonly, we will use your personal data in the following circumstances:</p>
                    <ul>
                        <li>Where we need to perform the contract we are about to enter into or have entered into with you.</li>
                        <li>Where it is necessary for our legitimate interests and your interests and fundamental rights do not override those interests.</li>
                        <li>Where we need to comply with a legal obligation.</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Data Security</h2>
                    <p>
                        We have put in place appropriate security measures to prevent your personal data from being accidentally lost, used or accessed in an unauthorized way, altered or disclosed.
                        In addition, we limit access to your personal data to those employees, agents, contractors and other third parties who have a business need to know.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Your Legal Rights</h2>
                    <p>Under certain circumstances, you have rights under data protection laws in relation to your personal data, including the right to:</p>
                    <ul>
                        <li>Request access to your personal data.</li>
                        <li>Request correction of your personal data.</li>
                        <li>Request erasure of your personal data.</li>
                        <li>Object to processing of your personal data.</li>
                        <li>Request restriction of processing your personal data.</li>
                        <li>Request transfer of your personal data.</li>
                        <li>Right to withdraw consent.</li>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h2>Contact Us</h2>
                    <p>
                        If you have any questions about this privacy policy or our privacy practices, please contact us:
                    </p>
                    <ul>
                    <li>Email: subdisystem@gmail.com</li>
                    <li>Phone: 09672688658</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';
?>
