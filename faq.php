<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>FAQ - Orchidee LLC | Frequently Asked Questions</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="Frequently asked questions about Orchidee LLC, NCLEX preparation, nursing registration, and our services" name="description">

        <!-- Google Web Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="img/orchideelogo.png">
        <link rel="apple-touch-icon" href="img/orchideelogo.png">

        <!-- Icon Font Stylesheet -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

        <!-- Libraries Stylesheet -->
        <link rel="stylesheet" href="lib/animate/animate.min.css"/>
        <link href="lib/lightbox/css/lightbox.min.css" rel="stylesheet">
        <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

        <!-- Customized Bootstrap Stylesheet -->
        <link href="css/bootstrap.min.css" rel="stylesheet">

        <!-- Template Stylesheet -->
        <link href="css/style.css" rel="stylesheet">
    </head>

    <body>

        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->


        <?php include 'includes/menu-dynamic.php'; ?>


        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('img/ban.jpeg') center/cover no-repeat;">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Frequently Asked Questions</h4>
                <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white">Home</a></li>
                    <li class="breadcrumb-item active text-primary">FAQ</li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->


        <!-- FAQ Start -->
        <div class="container-fluid bg-light py-5">
            <div class="container py-5">
                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="text-center mb-5 wow fadeInUp" data-wow-delay="0.1s">
                            <h2 class="text-primary mb-3">Questions Fréquemment Posées</h2>
                            <p class="text-muted">Trouvez les réponses aux questions les plus courantes sur Orchidee LLC, nos services et le processus d'inscription.</p>
                        </div>

                        <div class="accordion" id="faqAccordion">
                            <!-- Question 1 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.1s">
                                <h2 class="accordion-header" id="heading1">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>What is Orchidee?</strong>
                                    </button>
                                </h2>
                                <div id="collapse1" class="accordion-collapse collapse show" aria-labelledby="heading1" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Orchidee is an agency that helps nurses from overseas in the process to register to a board of nursing. We also offer review class to help them with the NCLEX preparedness.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 2 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.2s">
                                <h2 class="accordion-header" id="heading2">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Is Orchidee a nursing school?</strong>
                                    </button>
                                </h2>
                                <div id="collapse2" class="accordion-collapse collapse" aria-labelledby="heading2" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        No, Orchidee is not a nursing school.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 2A -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.2s">
                                <h2 class="accordion-header" id="heading2A">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2A" aria-expanded="false" aria-controls="collapse2A">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Can I register to a board of nursing if I don't have a nursing license?</strong>
                                    </button>
                                </h2>
                                <div id="collapse2A" class="accordion-collapse collapse" aria-labelledby="heading2A" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Yes you can but you need to have a diploma in nursing from your school.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 3 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.3s">
                                <h2 class="accordion-header" id="heading3">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Are the classes conducted online or in person?</strong>
                                    </button>
                                </h2>
                                <div id="collapse3" class="accordion-collapse collapse" aria-labelledby="heading3" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        The classes are conducting online.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 4 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.4s">
                                <h2 class="accordion-header" id="heading4">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4" aria-expanded="false" aria-controls="collapse4">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>What is the schedule for the classes?</strong>
                                    </button>
                                </h2>
                                <div id="collapse4" class="accordion-collapse collapse" aria-labelledby="heading4" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Depending on the session, classes are held Everyday except Saturday. Visit the website for more information.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 5 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.5s">
                                <h2 class="accordion-header" id="heading5">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5" aria-expanded="false" aria-controls="collapse5">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Do you have morning classes?</strong>
                                    </button>
                                </h2>
                                <div id="collapse5" class="accordion-collapse collapse" aria-labelledby="heading5" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        No we don't have classes in the morning.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 6 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.6s">
                                <h2 class="accordion-header" id="heading6">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Do you have prerecorded lessons?</strong>
                                    </button>
                                </h2>
                                <div id="collapse6" class="accordion-collapse collapse" aria-labelledby="heading6" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        For now, we don't have prerecorded lessons but soon, we will.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 7 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.7s">
                                <h2 class="accordion-header" id="heading7">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse7" aria-expanded="false" aria-controls="collapse7">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Do we have access to record the lessons?</strong>
                                    </button>
                                </h2>
                                <div id="collapse7" class="accordion-collapse collapse" aria-labelledby="heading7" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Unfortunately you can't record the class.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 8 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.8s">
                                <h2 class="accordion-header" id="heading8">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse8" aria-expanded="false" aria-controls="collapse8">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Can we access the courses in our own schedule?</strong>
                                    </button>
                                </h2>
                                <div id="collapse8" class="accordion-collapse collapse" aria-labelledby="heading8" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        The classes are conducted on a regular schedule.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 9 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="0.9s">
                                <h2 class="accordion-header" id="heading9">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse9" aria-expanded="false" aria-controls="collapse9">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>How much is the review class?</strong>
                                    </button>
                                </h2>
                                <div id="collapse9" class="accordion-collapse collapse" aria-labelledby="heading9" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        If you need information on the price, please book a call.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 10 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.0s">
                                <h2 class="accordion-header" id="heading10">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse10" aria-expanded="false" aria-controls="collapse10">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>How long is the review session?</strong>
                                    </button>
                                </h2>
                                <div id="collapse10" class="accordion-collapse collapse" aria-labelledby="heading10" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        The length varies between 3 to 6 months.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 11 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.1s">
                                <h2 class="accordion-header" id="heading11">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse11" aria-expanded="false" aria-controls="collapse11">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Do you help people find job after they pass the NCLEX?</strong>
                                    </button>
                                </h2>
                                <div id="collapse11" class="accordion-collapse collapse" aria-labelledby="heading11" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Yes we do but this is one of the different services that Orchidee offers.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 12 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.2s">
                                <h2 class="accordion-header" id="heading12">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse12" aria-expanded="false" aria-controls="collapse12">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Do you help with license endorsement?</strong>
                                    </button>
                                </h2>
                                <div id="collapse12" class="accordion-collapse collapse" aria-labelledby="heading12" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Yes we do.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 13 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.3s">
                                <h2 class="accordion-header" id="heading13">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse13" aria-expanded="false" aria-controls="collapse13">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>What do I need to register to a board of nursing?</strong>
                                    </button>
                                </h2>
                                <div id="collapse13" class="accordion-collapse collapse" aria-labelledby="heading13" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        You need your nursing diploma and other documents that you can find on our website at the time of registration.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 14 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.4s">
                                <h2 class="accordion-header" id="heading14">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse14" aria-expanded="false" aria-controls="collapse14">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>How long does the registration process take?</strong>
                                    </button>
                                </h2>
                                <div id="collapse14" class="accordion-collapse collapse" aria-labelledby="heading14" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        It depends on how long your school takes to send your transcripts and also the board of nursing. So it may varies from 3 months to a year.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 15 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.5s">
                                <h2 class="accordion-header" id="heading15">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse15" aria-expanded="false" aria-controls="collapse15">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>How long after the approval can I schedule the test?</strong>
                                    </button>
                                </h2>
                                <div id="collapse15" class="accordion-collapse collapse" aria-labelledby="heading15" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        You will have the Authorization to Test (ATT) valid for 6 months. This is the time you would need to schedule your test.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 16 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.6s">
                                <h2 class="accordion-header" id="heading16">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse16" aria-expanded="false" aria-controls="collapse16">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Do you offer tutoring one on one?</strong>
                                    </button>
                                </h2>
                                <div id="collapse16" class="accordion-collapse collapse" aria-labelledby="heading16" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Yes we do.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 17 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.7s">
                                <h2 class="accordion-header" id="heading17">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse17" aria-expanded="false" aria-controls="collapse17">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>How long does the tutoring last?</strong>
                                    </button>
                                </h2>
                                <div id="collapse17" class="accordion-collapse collapse" aria-labelledby="heading17" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        It varies on the clients needs but generally it is done over 6 weeks minimum.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 18 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.8s">
                                <h2 class="accordion-header" id="heading18">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse18" aria-expanded="false" aria-controls="collapse18">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Do you give a certificate of completion after the review?</strong>
                                    </button>
                                </h2>
                                <div id="collapse18" class="accordion-collapse collapse" aria-labelledby="heading18" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Yes we do.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 19 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="1.9s">
                                <h2 class="accordion-header" id="heading19">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse19" aria-expanded="false" aria-controls="collapse19">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>Do you have an office?</strong>
                                    </button>
                                </h2>
                                <div id="collapse19" class="accordion-collapse collapse" aria-labelledby="heading19" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        No. We receive the documents online and we conduct the classes online.
                                    </div>
                                </div>
                            </div>

                            <!-- Question 20 -->
                            <div class="accordion-item bg-white rounded mb-3 shadow-sm wow fadeInUp" data-wow-delay="2.0s">
                                <h2 class="accordion-header" id="heading20">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse20" aria-expanded="false" aria-controls="collapse20">
                                        <i class="fas fa-question-circle text-primary me-3"></i>
                                        <strong>What is Orchidee's passing rate?</strong>
                                    </button>
                                </h2>
                                <div id="collapse20" class="accordion-collapse collapse" aria-labelledby="heading20" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Our passing rate is 97% as of now.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact CTA -->
                        <div class="text-center mt-5 wow fadeInUp" data-wow-delay="0.3s">
                            <div class="bg-primary rounded p-5 text-white">
                                <h3 class="mb-3">Vous avez d'autres questions ?</h3>
                                <p class="mb-4">N'hésitez pas à nous contacter pour obtenir plus d'informations.</p>
                                <a href="contact.html" class="btn btn-light rounded-pill py-3 px-5 me-3">
                                    <i class="fas fa-envelope me-2"></i>Contactez-nous
                                </a>
                                <a href="consultation.html" class="btn btn-outline-light rounded-pill py-3 px-5">
                                    <i class="fas fa-calendar me-2"></i>Réserver une consultation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- FAQ End -->


        <?php include 'includes/footer.php'; ?>
        <a href="#" class="btn btn-primary btn-lg-square rounded-circle back-to-top"><i class="fa fa-arrow-up"></i></a>   


        <!-- JavaScript Libraries -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/wow/wow.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/counterup/counterup.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        

        <!-- Template Javascript -->
        <script src="js/main.js"></script>
    </body>

</html>
