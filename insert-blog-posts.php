<?php
/**
 * Script pour insérer 2 articles de blog d'exemple
 * Exécutez ce script une seule fois via votre navigateur ou en ligne de commande
 */

require_once 'config/database.php';

try {
    $conn = getDBConnection();
    
    // Vérifier si la table blog_posts existe
    $tableCheck = $conn->query("SHOW TABLES LIKE 'blog_posts'");
    if ($tableCheck->num_rows === 0) {
        die("Error: Table 'blog_posts' does not exist. Please run database/create_admin_tables.sql first.");
    }
    
    // Récupérer le premier admin comme auteur (ou créer un auteur par défaut)
    $authorResult = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($authorResult->num_rows === 0) {
        // Créer un utilisateur admin par défaut si aucun n'existe
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (first_name, last_name, email, password, role) VALUES ('Admin', 'User', 'admin@orchideellc.com', '$defaultPassword', 'admin')");
        $authorId = $conn->insert_id;
    } else {
        $authorRow = $authorResult->fetch_assoc();
        $authorId = $authorRow['id'];
    }
    
    // Vérifier si les articles existent déjà
    $existingCheck = $conn->query("SELECT COUNT(*) as count FROM blog_posts WHERE title LIKE 'Top 10 NCLEX%' OR title LIKE 'Understanding Credential%'");
    $existing = $existingCheck->fetch_assoc();
    
    if ($existing['count'] > 0) {
        echo "<h2>Articles déjà existants</h2>";
        echo "<p>Les articles de blog ont déjà été insérés. Vérifiez votre base de données.</p>";
        echo "<p><a href='admin/blog.php'>Voir les articles dans l'admin</a></p>";
        exit;
    }
    
    // Article 1: Top 10 NCLEX Study Strategies That Work
    $article1 = [
        'title' => 'Top 10 NCLEX Study Strategies That Work',
        'content' => '<h2>Introduction</h2>
    <p>Preparing for the NCLEX exam can be overwhelming, but with the right strategies, you can maximize your study time and boost your confidence. Here are the top 10 proven study strategies that have helped hundreds of nurses pass the NCLEX on their first attempt.</p>
    
    <h3>1. Create a Structured Study Schedule</h3>
    <p>Consistency is key when preparing for the NCLEX. Create a daily study schedule that includes specific times for reviewing content, taking practice questions, and reviewing your mistakes. Stick to your schedule as much as possible to build a strong study habit.</p>
    
    <h3>2. Focus on High-Yield Topics</h3>
    <p>While it\'s important to review all content, prioritize high-yield topics that appear frequently on the exam. These include:</p>
    <ul>
        <li>Pharmacology and medication administration</li>
        <li>Patient safety and infection control</li>
        <li>Prioritization and delegation</li>
        <li>Medical-surgical nursing</li>
        <li>Mental health nursing</li>
    </ul>
    
    <h3>3. Practice with NCLEX-Style Questions</h3>
    <p>Familiarize yourself with the NCLEX question format by practicing with hundreds of questions. Focus on understanding why answers are correct or incorrect, not just memorizing answers.</p>
    
    <h3>4. Use Active Learning Techniques</h3>
    <p>Instead of passively reading, engage in active learning:</p>
    <ul>
        <li>Create flashcards for key concepts</li>
        <li>Teach concepts to others or explain them out loud</li>
        <li>Draw diagrams and flowcharts</li>
        <li>Use mnemonics for memorization</li>
    </ul>
    
    <h3>5. Join a Study Group</h3>
    <p>Studying with peers can help you stay motivated and gain different perspectives on difficult topics. Join our OrchideeLLC study groups to connect with other NCLEX candidates.</p>
    
    <h3>6. Take Regular Practice Exams</h3>
    <p>Simulate the actual exam experience by taking full-length practice exams under timed conditions. This helps build stamina and reduces test anxiety.</p>
    
    <h3>7. Review Your Mistakes Thoroughly</h3>
    <p>Don\'t just move on after getting a question wrong. Take time to understand why you missed it and review the underlying concept. Keep a journal of your mistakes to identify patterns.</p>
    
    <h3>8. Prioritize Self-Care</h3>
    <p>Your physical and mental health directly impact your ability to study effectively. Ensure you get enough sleep, eat well, exercise, and take breaks to avoid burnout.</p>
    
    <h3>9. Use Multiple Study Resources</h3>
    <p>Don\'t rely on just one study resource. Combine textbooks, online courses, practice question banks, and video lectures to get a comprehensive understanding of the material.</p>
    
    <h3>10. Stay Positive and Confident</h3>
    <p>Believe in yourself and your preparation. Maintain a positive mindset and visualize yourself passing the exam. Confidence is a crucial component of NCLEX success.</p>
    
    <h2>Conclusion</h2>
    <p>Remember, passing the NCLEX is not just about memorizing facts—it\'s about understanding nursing concepts and applying critical thinking. With dedication, the right strategies, and support from OrchideeLLC, you can achieve your goal of becoming a licensed nurse in the United States.</p>
    
    <p><strong>Ready to start your NCLEX journey?</strong> <a href="consultation.html">Book a consultation</a> with our expert coaches today!</p>',
        'excerpt' => 'Discover proven study strategies that have helped hundreds of nurses pass the NCLEX on their first attempt. Learn how to maximize your study time and boost your confidence.',
        'category' => 'NCLEX Tips',
        'status' => 'published'
    ];
    
    // Article 2: Understanding Credential Evaluation for US Nursing
    $article2 = [
        'title' => 'Understanding Credential Evaluation for US Nursing',
        'content' => '<h2>Introduction</h2>
    <p>If you\'re an internationally educated nurse looking to practice in the United States, understanding the credential evaluation process is essential. This comprehensive guide will walk you through everything you need to know about credential evaluation, required documents, timelines, and how to avoid common mistakes.</p>
    
    <h3>What is Credential Evaluation?</h3>
    <p>Credential evaluation is the process of having your foreign nursing education and credentials assessed to determine their equivalency to U.S. nursing standards. This evaluation is required by most state boards of nursing before you can take the NCLEX and obtain a U.S. nursing license.</p>
    
    <h3>Why is Credential Evaluation Important?</h3>
    <p>State boards of nursing need to verify that your education meets U.S. standards before allowing you to take the NCLEX. The evaluation ensures:</p>
    <ul>
        <li>Your nursing education is equivalent to U.S. nursing programs</li>
        <li>You have completed the required coursework</li>
        <li>Your clinical hours meet minimum requirements</li>
        <li>Your credentials are authentic and valid</li>
    </ul>
    
    <h3>Who Performs Credential Evaluations?</h3>
    <p>Several organizations are authorized to perform credential evaluations for nurses:</p>
    <ul>
        <li><strong>Commission on Graduates of Foreign Nursing Schools (CGFNS)</strong> - Most commonly used</li>
        <li><strong>Educational Records Evaluation Service (ERES)</strong></li>
        <li><strong>International Education Research Foundation (IERF)</strong></li>
    </ul>
    <p>Check with your specific state board of nursing to determine which evaluation service they accept.</p>
    
    <h3>Required Documents for Credential Evaluation</h3>
    <p>Gathering the correct documents is crucial for a smooth evaluation process. You will typically need:</p>
    
    <h4>1. Educational Documents</h4>
    <ul>
        <li>Official transcripts from your nursing school</li>
        <li>Diploma or degree certificate</li>
        <li>Course descriptions and syllabi</li>
        <li>Clinical rotation records</li>
    </ul>
    
    <h4>2. Professional Documents</h4>
    <ul>
        <li>Current nursing license from your home country</li>
        <li>License verification from your licensing board</li>
        <li>Employment verification letters</li>
    </ul>
    
    <h4>3. Personal Documents</h4>
    <ul>
        <li>Valid passport</li>
        <li>Birth certificate</li>
        <li>Marriage certificate (if applicable)</li>
        <li>Name change documents (if applicable)</li>
    </ul>
    
    <h3>Common Mistakes to Avoid</h3>
    <p>Many nurses encounter delays due to avoidable mistakes. Here are the most common issues:</p>
    
    <h4>1. Incomplete Documentation</h4>
    <p>Missing or incomplete documents can delay your evaluation by weeks or months. Double-check all requirements before submitting.</p>
    
    <h4>2. Not Getting Documents Translated</h4>
    <p>If your documents are not in English, you must have them professionally translated by a certified translator.</p>
    
    <h4>3. Using the Wrong Evaluation Service</h4>
    <p>Each state board may accept different evaluation services. Verify which service your state requires before starting the process.</p>
    
    <h4>4. Not Following Up</h4>
    <p>Keep track of your application status and follow up regularly. Don\'t assume everything is processing smoothly.</p>
    
    <h4>5. Waiting Too Long to Start</h4>
    <p>The credential evaluation process can take 3-6 months or longer. Start early to avoid delays in your NCLEX timeline.</p>
    
    <h3>Timeline Expectations</h3>
    <p>Understanding the timeline helps you plan effectively:</p>
    <ul>
        <li><strong>Document Gathering:</strong> 2-4 weeks</li>
        <li><strong>Translation (if needed):</strong> 1-2 weeks</li>
        <li><strong>Evaluation Processing:</strong> 8-12 weeks</li>
        <li><strong>State Board Review:</strong> 4-8 weeks</li>
    </ul>
    <p><strong>Total Time:</strong> Approximately 4-6 months from start to finish</p>
    
    <h3>Tips for a Smooth Process</h3>
    <ol>
        <li><strong>Start Early:</strong> Begin gathering documents as soon as you decide to pursue U.S. licensure</li>
        <li><strong>Stay Organized:</strong> Keep copies of all documents and correspondence</li>
        <li><strong>Communicate Clearly:</strong> Respond promptly to any requests for additional information</li>
        <li><strong>Seek Professional Help:</strong> Consider working with a credential evaluation service or consultant</li>
        <li><strong>Be Patient:</strong> The process takes time, but thoroughness is more important than speed</li>
    </ol>
    
    <h3>How OrchideeLLC Can Help</h3>
    <p>At OrchideeLLC, we understand the challenges of the credential evaluation process. Our team provides:</p>
    <ul>
        <li>Step-by-step guidance through the entire process</li>
        <li>Document checklist and review</li>
        <li>Timeline planning and management</li>
        <li>Support when issues arise</li>
        <li>Coordination with evaluation services</li>
    </ul>
    
    <h2>Conclusion</h2>
    <p>Credential evaluation is a critical step in your journey to becoming a U.S. licensed nurse. While the process can seem daunting, proper preparation and organization can make it manageable. Remember, you don\'t have to navigate this alone—OrchideeLLC is here to support you every step of the way.</p>
    
    <p><strong>Need help with your credential evaluation?</strong> <a href="consultation.html">Schedule a consultation</a> with our expert team today!</p>',
        'excerpt' => 'Navigate the credential evaluation process with confidence. Learn what documents you need, timelines to expect, and how to avoid common mistakes that delay your application.',
        'category' => 'Licensure',
        'status' => 'published'
    ];
    
    // Insérer les articles
    $stmt = $conn->prepare("INSERT INTO blog_posts (title, content, excerpt, category, status, author_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    
    foreach ([$article1, $article2] as $article) {
        $stmt->bind_param("sssssi", 
            $article['title'],
            $article['content'],
            $article['excerpt'],
            $article['category'],
            $article['status'],
            $authorId
        );
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Article inséré: " . htmlspecialchars($article['title']) . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Erreur lors de l'insertion: " . htmlspecialchars($article['title']) . "</p>";
        }
    }
    
    $stmt->close();
    $conn->close();
    
    echo "<h2 style='color: green;'>✓ Articles de blog insérés avec succès!</h2>";
    echo "<div style='padding: 20px; background: #f0f0f0; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Prochaines étapes:</h3>";
    echo "<ul>";
    echo "<li><a href='admin/blog.php' target='_blank'>Gérer les articles dans l'administration</a></li>";
    echo "<li><a href='blog.php' target='_blank'>Voir les articles sur le site public</a></li>";
    echo "<li>Vous pouvez maintenant modifier ou supprimer ces articles depuis l'admin</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Erreur</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Assurez-vous que:</p>";
    echo "<ul>";
    echo "<li>La table 'blog_posts' existe (exécutez database/create_admin_tables.sql)</li>";
    echo "<li>Vous avez au moins un utilisateur admin dans la table 'users'</li>";
    echo "<li>La connexion à la base de données est configurée correctement</li>";
    echo "</ul>";
}
?>

