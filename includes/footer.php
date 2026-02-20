<!-- Footer Start - COMPLETELY ISOLATED -->
        <div class="container-fluid footer py-5" style="margin: 0 !important; padding-bottom: 0 !important; width: 100vw !important; margin-left: -50vw !important; margin-right: -50vw !important; left: 50% !important; right: 50% !important; position: relative !important;">
            <div class="container" style="margin: 0 auto !important; padding-bottom: 0 !important;">
                <div class="row g-4">
                    <!-- Section 1: Logo et reseaux sociaux -->
                    <div class="col-lg-3 col-md-6">
                        <div class="footer-item">
                            <a href="index.php" class="p-0 mb-4 d-block">
                                <img src="img/orchideelogo.png" alt="Orchidee LLC" style="height: 50px;">
                            </a>
                            <div class="footer-btn d-flex">
                                <a class="btn btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-facebook-f"></i></a>
                                <a class="btn btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-twitter"></i></a>
                                <a class="btn btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-instagram"></i></a>
                                <a class="btn btn-sm-square rounded-circle me-0" href="#"><i class="fab fa-linkedin-in"></i></a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 2: Registration, Services, Consultation -->
                    <div class="col-lg-3 col-md-6">
                        <div class="footer-item">
                            <h4 class="text-white mb-4">Quick Links</h4>
                            <div class="d-flex flex-column gap-2">
                                <a href="register.php" class="text-white-50 text-decoration-none">Registration</a>
                                <a href="#" class="text-white-50 text-decoration-none">Our Services</a>
                                <a href="consultation.html" class="text-white-50 text-decoration-none">Book a Consultation</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 3: About us, Blog post, contact -->
                    <div class="col-lg-3 col-md-6">
                        <div class="footer-item">
                            <h4 class="text-white mb-4">Company</h4>
                            <div class="d-flex flex-column gap-2">
                                <a href="about.php" class="text-white-50 text-decoration-none">About Us</a>
                                <a href="blog.php" class="text-white-50 text-decoration-none">Blog Post</a>
                                <a href="faq.php" class="text-white-50 text-decoration-none">FAQ</a>
                                <a href="contact.html" class="text-white-50 text-decoration-none">Contact</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 4: Subscribe now + Available hours -->
                    <div class="col-lg-3 col-md-6">
                        <div class="footer-item">
                            <p class="text-white mb-3">
                                <i class="far fa-clock me-2"></i><strong>Available hours</strong><br>
                                <span class="text-white-50">9:00 AM – 5:00 PM</span>
                            </p>
                            <p class="text-white mb-3">
                                <i class="fa fa-phone me-2"></i><strong>Phone</strong><br>
                                <a href="tel:+18622367705" class="text-white-50 text-decoration-none">862-236-7705</a>
                            </p>
                            <h4 class="text-white mb-4">Subscribe Now</h4>
                            <p class="text-white-50 mb-3">Subscribe to get the latest updates and news.</p>
                            <div class="position-relative">
                                <input class="form-control bg-transparent text-white w-100 py-3 ps-4 pe-5" type="text" placeholder="Your Email">
                                <button type="button" class="btn btn-primary py-2 px-3 position-absolute top-0 end-0 mt-2 me-2">Subscribe</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4 pt-4 border-top border-secondary">
                    <div class="col-12 text-center">
                        <p class="text-white-50 mb-0 small">
                            <i class="fas fa-copyright me-1"></i> 2025 Orchidee LLC. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer End -->
        
        <!-- Chat Button -->
        <?php if (file_exists(__DIR__ . '/chat-button.php')) { include __DIR__ . '/chat-button.php'; } ?>
