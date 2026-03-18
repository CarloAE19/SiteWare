<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
require_once 'Connection/db.php';

$role = $_SESSION['user_role'] ?? 'requestor';

include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white text-center mb-4" style="border-top: 5px solid var(--gb-yellow) !important;">

        <!-- Two Logos Placeholder (Replace src attributes with your actual logo paths) -->
        <div class="d-flex justify-content-center align-items-center gap-3 gap-md-5 mb-4 px-2">
            <img src="assets/img/scc_placeholder.png" alt="Logo 1 Placeholder" style="width: 45%; max-width: 220px; height: auto; max-height: 120px; object-fit: contain;">
            <img src="assets/img/gb_placeholder.png" alt="Logo 2 Placeholder" style="width: 45%; max-width: 220px; height: auto; max-height: 120px; object-fit: contain;">
        </div>

        <h1 class="fw-bold text-dark display-5 mb-3">About <span style="color: var(--gb-blue);">The Medyas</span></h1>
        <p class="lead text-muted mb-0 mx-auto" style="max-width: 700px;">
            We are the dedicated team behind the SiteWare. Our goal is to provide a seamless, efficient, and robust inventory management system for Genetian Builders & Enterprises Inc.
        </p>
    </div>

    <div class="row g-4 justify-content-center mt-5 pt-4">
        <!-- Developer -->
        <div class="col-12 col-md-4 mt-5 mobile-mt">
            <div class="card h-100 border-0 shadow-sm text-center px-4 pb-4 pt-0 hover-lift" style="border-radius: 15px;">
                <div class="mb-3" style="margin-top: -60px;">
                    <!-- Replace src with your developer photo -->
                    <img src="assets/img/dev_photo_placeholder.jpg" alt="Developer Image" class="rounded-circle shadow-sm object-fit-cover" style="width: 140px; height: 140px; border: 4px solid var(--gb-blue); background-color: #f8f9fa;">
                </div>
                <!-- Replace text inside h3 with the Developer's Name -->
                <h3 class="fw-bold text-dark mb-1">Angelo Carlo P. Pedrosa</h3>
                <h6 class="fw-bold mb-2" style="color: var(--gb-blue); letter-spacing: 1px; text-transform: uppercase;">Developer</h6>
                <p class="text-muted mb-3" style="font-size: 0.9rem;">Architects and builds the core logic, database interactions, and user interface of the Inventory Management System to ensure a high-performance experience.</p>
                <div class="d-flex justify-content-center gap-3 mt-auto">
                    <a href="https://www.facebook.com/CarloAE01" target="_blank" class="text-secondary hover-primary transition"><i class="bi bi-facebook fs-4"></i></a>
                    <a href="https://www.instagram.com/pedrosangelo11" target="_blank" class="text-secondary hover-danger transition"><i class="bi bi-instagram fs-4"></i></a>
                </div>
            </div>
        </div>

        <!-- Project Manager -->
        <div class="col-12 col-md-4 mt-5 mobile-mt">
            <div class="card h-100 border-0 shadow-sm text-center px-4 pb-4 pt-0 hover-lift" style="border-radius: 15px;">
                <div class="mb-3" style="margin-top: -60px;">
                    <!-- Replace src with your project manager photo -->
                    <img src="assets/img/pm_photo_placeholder.jpg" alt="Project Manager Image" class="rounded-circle shadow-sm object-fit-cover" style="width: 140px; height: 140px; border: 4px solid var(--gb-dark); background-color: #f8f9fa;">
                </div>
                <!-- Replace text inside h3 with the Project Manager's Name -->
                <h3 class="fw-bold text-dark mb-1">Jahzeel James A. Jakosalem</h3>
                <h6 class="fw-bold text-dark mb-2" style="letter-spacing: 1px; text-transform: uppercase;">Project Manager</h6>
                <p class="text-muted mb-3" style="font-size: 0.9rem;">Oversees project progress, ensures requirements are met, and bridges communication between the team and stakeholders to deliver on time.</p>
                <div class="d-flex justify-content-center gap-3 mt-auto">
                    <a href="https://www.facebook.com/jahzeel.jakosalem" target="_blank" class="text-secondary hover-primary transition"><i class="bi bi-facebook fs-4"></i></a>
                    <a href="https://www.instagram.com/1nonlyjahz" target="_blank" class="text-secondary hover-danger transition"><i class="bi bi-instagram fs-4"></i></a>
                </div>
            </div>
        </div>

        <!-- Quality Assurance -->
        <div class="col-12 col-md-4 mt-5 mobile-mt">
            <div class="card h-100 border-0 shadow-sm text-center px-4 pb-4 pt-0 hover-lift" style="border-radius: 15px;">
                <div class="mb-3" style="margin-top: -60px;">
                    <!-- Replace src with your QA photo -->
                    <img src="assets/img/qa_photo_placeholder.jpg" alt="Quality Assurance Image" class="rounded-circle shadow-sm object-fit-cover" style="width: 140px; height: 140px; border: 4px solid var(--gb-yellow); background-color: #f8f9fa;">
                </div>
                <!-- Replace text inside h3 with the QA's Name -->
                <h3 class="fw-bold text-dark mb-1">LJ D. Caballero</h3>
                <h6 class="fw-bold mb-2" style="color: #d39e00; letter-spacing: 1px; text-transform: uppercase;">Quality Assurance</h6>
                <p class="text-muted mb-3" style="font-size: 0.9rem;">Rigorously tests system functionalities, catches bugs, and verifies that the software meets quality standards before deployment.</p>
                <div class="d-flex justify-content-center gap-3 mt-auto">
                    <a href="https://www.facebook.com/LjayCute09" target="_blank" class="text-secondary hover-primary transition"><i class="bi bi-facebook fs-4"></i></a>
                    <a href="https://www.instagram.com/itsljayyyyyyy" target="_blank" class="text-secondary hover-danger transition"><i class="bi bi-instagram fs-4"></i></a>
                </div>
            </div>
        </div>
    </div>

    <style>
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-10px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, .15) !important;
        }

        /* Mobile specific margin fixes because of the overlapping image */
        @media (max-width: 768px) {
            .mobile-mt {
                margin-top: 5rem !important;
            }
        }
    </style>
</div>

<?php include 'layout/footer.php'; ?>