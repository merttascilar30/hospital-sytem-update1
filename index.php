<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çukurova Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <i class="fa-solid fa-hospital-user me-2"></i>Çukurova Hospital
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-3">
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link fw-semibold" href="#contact">Contact</a></li>
                    <li class="nav-item ms-lg-3">
                        <a href="login.php" class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section id="home" class="py-5 bg-light">
        <div class="container py-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-5 text-center text-lg-start">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=600&q=80" alt="Expert Doctor" class="img-fluid rounded-4 shadow-lg hero-img">
                </div>
                <div class="col-lg-7">
                    <h1 class="display-4 fw-bold text-dark mb-4">The Best For Your Health</h1>
                    <p class="lead text-muted mb-5">We are with you with our modern equipment and expert staff.</p>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature-card card h-100 border-0 shadow-sm p-4 rounded-4 text-center">
                                <i class="fa-solid fa-user-doctor text-danger fs-1 mb-3"></i>
                                <h5 class="fw-bold text-danger">Patient-Oriented Approach</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card card h-100 border-0 shadow-sm p-4 rounded-4 text-center">
                                <i class="fa-solid fa-book-open text-danger fs-1 mb-3"></i>
                                <h5 class="fw-bold text-danger">Strong References</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card card h-100 border-0 shadow-sm p-4 rounded-4 text-center">
                                <i class="fa-solid fa-shield-halved text-danger fs-1 mb-3"></i>
                                <h5 class="fw-bold text-danger">Reliability</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card card h-100 border-0 shadow-sm p-4 rounded-4 text-center">
                                <i class="fa-solid fa-users text-danger fs-1 mb-3"></i>
                                <h5 class="fw-bold text-danger">Expert Staff</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="about-section py-5 text-white">
        <div class="container py-5 text-center">
            <h2 class="display-6 fw-bold mb-5">Why Choose Us?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <i class="fa-solid fa-calendar-check fs-1 mb-3"></i>
                    <h4 class="fw-semibold">Make an appointment</h4>
                    <p class="small opacity-75">Easily schedule your visit using our modern appointment system.</p>
                </div>
                <div class="col-md-4">
                    <i class="fa-solid fa-user-nurse fs-1 mb-3"></i>
                    <h4 class="fw-semibold">Help by specialist</h4>
                    <p class="small opacity-75">Get treated by industry-leading doctors and specialists.</p>
                </div>
                <div class="col-md-4">
                    <i class="fa-solid fa-file-medical fs-1 mb-3"></i>
                    <h4 class="fw-semibold">Get diagnostic report</h4>
                    <p class="small opacity-75">Access your medical history and test reports seamlessly.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="py-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold">Contact Us</h2>
                        <p class="text-muted">Send us your message and we will get back to you.</p>
                    </div>
                    <div class="card border-0 shadow-lg p-4 rounded-4">
                        <form action="#" method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control bg-light" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control bg-light" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control bg-light" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea class="form-control bg-light" rows="4" required></textarea>
                                </div>
                                <div class="col-12 text-center mt-4">
                                    <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill fw-bold">Send Message</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>