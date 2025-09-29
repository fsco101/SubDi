<?php

$pageTitle = "Terms and Conditions";
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    include __DIR__ . '/includes/header.php';
} else {
    include __DIR__ . '/includes/index_header.php';
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h1>Terms and Conditions</h1>
            <p class="text-muted">Last updated: <?php echo date('F d, Y'); ?></p>
            <hr>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Introduction</h2>
                    <p>
                        These terms and conditions ("Terms") govern your use of SubDi.Assoc's website and services ("Service").
                        By accessing or using the Service, you agree to be bound by these Terms. If you disagree with any part of the terms,
                        then you do not have permission to access the Service.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Use License</h2>
                    <p>
                        Permission is granted to temporarily download one copy of the materials on SubDi.Assoc's website for personal,
                        non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license
                        you may not:
                    </p>
                    <ul>
                        <li>Modify or copy the materials;</li>
                        <li>Use the materials for any commercial purpose or for any public display;</li>
                        <li>Attempt to reverse engineer any software contained on SubDi.Assoc's website;</li>
                        <li>Remove any copyright or other proprietary notations from the materials; or</li>
                        <li>Transfer the materials to another person or "mirror" the materials on any other server.</li>
                    </ul>
                    <p>
                        This license shall automatically terminate if you violate any of these restrictions and may be terminated by
                        SubDi.Assoc at any time. Upon terminating your viewing of these materials or upon the termination of this license,
                        you must destroy any downloaded materials in your possession whether in electronic or printed format.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Disclaimer</h2>
                    <p>
                        The materials on SubDi.Assoc's website are provided on an 'as is' basis. SubDi.Assoc makes no warranties,
                        expressed or implied, and hereby disclaims and negates all other warranties including, without limitation, implied warranties
                        or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other
                        violation of rights.
                    </p>
                    <p>
                        Further, SubDi.Assoc does not warrant or make any representations concerning the accuracy, likely results, or
                        reliability of the use of the materials on its website or otherwise relating to such materials or on any sites linked to this site.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Limitations</h2>
                    <p>
                        In no event shall SubDi.Assoc or its suppliers be liable for any damages (including, without limitation, damages for
                        loss of data or profit, or due to business interruption) arising out of the use or inability to use the materials on
                        SubDi.Assoc's website, even if SubDi.Assoc or a SubDi.Assoc authorized representative has been notified
                        orally or in writing of the possibility of such damage.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Accuracy of Materials</h2>
                    <p>
                        The materials appearing on SubDi.Assoc's website could include technical, typographical, or photographic errors.
                        SubDi.Assoc does not warrant that any of the materials on its website are accurate, complete or current.
                        SubDi.Assoc may make changes to the materials contained on its website at any time without notice.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Links</h2>
                    <p>
                        SubDi.Assoc has not reviewed all of the sites linked to its website and is not responsible for the contents of any such linked site.
                        The inclusion of any link does not imply endorsement by SubDi.Assoc of the site. Use of any such linked website is at the user's own risk.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Modifications</h2>
                    <p>
                        SubDi.Assoc may revise these Terms of Service for its website at any time without notice. By using this website,
                        you are agreeing to be bound by the then current version of these Terms and Conditions.
                    </p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h2>Contact Us</h2>
                    <p>
                        If you have any questions about these Terms, please contact us:
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
