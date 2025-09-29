<?php

$pageTitle = "About Us";
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    include __DIR__ . '/includes/header.php';
} else {
    include __DIR__ . '/includes/index_header.php';
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h1>About Us</h1>
            <hr>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Our Mission</h2>
                    <p>
                        At SubDi.Assoc's, our mission is to provide high-quality services and solutions
                        that meet the needs of our customers. We strive to innovate and excel in everything we do.
                    </p>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h2>Our Team</h2>
                    <p>
                        We have a dedicated team of professionals who are experts in their respective fields.
                        Our combined experience and knowledge allow us to deliver exceptional results for our clients.
                    </p>
                    
                    <!-- Team members could go here -->
                    <div class="row mt-4 justify-content-center">
                        <div class="col-md-3 text-center mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column align-items-center">
                                    <div class="team-img-container" style="width: 150px; height: 150px; overflow: hidden; border-radius: 50%; margin-bottom: 15px;">
                                        <img src="/subdisystem/assets/ramon.png" alt="Ramon N. Francisco Jr." class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <h5>Ramon N. Francisco Jr.</h5>
                                    <p class="text-muted">Lead Programmer & Researcher</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column align-items-center">
                                    <div class="team-img-container" style="width: 150px; height: 150px; overflow: hidden; border-radius: 50%; margin-bottom: 15px;">
                                        <img src="/subdisystem/assets/ronald.png" alt="Ronald O. Ajusan" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <h5>Ronald O. Ajusan</h5>
                                    <p class="text-muted">Programmer & Researcher</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column align-items-center">
                                    <div class="team-img-container" style="width: 150px; height: 150px; overflow: hidden; border-radius: 50%; margin-bottom: 15px;">
                                        <img src="/subdisystem/assets/mark.jpg" alt="Mark Kent I. Del Rosario" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <h5>Mark Kent I. Del Rosario</h5>
                                    <p class="text-muted">Programmer & Researcher</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column align-items-center">
                                    <div class="team-img-container" style="width: 150px; height: 150px; overflow: hidden; border-radius: 50%; margin-bottom: 15px;">
                                        <img src="/subdisystem/assets/dweight.png" alt="Dweight Mckaine L. Mandawe" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <h5>Dweight Mckaine L. Mandawe</h5>
                                    <p class="text-muted">Programmer & Researcher</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h2>Contact Us</h2>
                    <p>
                        Have questions or want to learn more about our services? Contact us:
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
