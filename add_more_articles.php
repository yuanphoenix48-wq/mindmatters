<?php
// Add more articles to existing articles table
require_once 'connect.php';

echo "<h2>Adding More Articles to Existing Table...</h2>";

// Check if articles table exists and get its structure
$result = $conn->query("DESCRIBE articles");
if (!$result) {
    echo "<p style='color: red;'>Error: Articles table not found. Please run setup_articles.php first.</p>";
    exit;
}

$columns = $result->fetch_all(MYSQLI_ASSOC);
$column_names = array_column($columns, 'Field');
echo "<p>Found articles table with columns: " . implode(', ', $column_names) . "</p>";

// Check which columns are available
$has_image_url = in_array('image_url', $column_names);
$has_read_time = in_array('read_time', $column_names);
$has_featured = in_array('featured', $column_names);

echo "<p>Available columns: image_url=" . ($has_image_url ? 'YES' : 'NO') . 
     ", read_time=" . ($has_read_time ? 'YES' : 'NO') . 
     ", featured=" . ($has_featured ? 'YES' : 'NO') . "</p>";

// Additional articles to add
$new_articles = [
    [
        'title' => 'Mindfulness and Meditation for Students',
        'summary' => 'Learn how mindfulness practices can reduce stress, improve focus, and enhance your overall well-being as a student.',
        'link' => 'https://www.mindful.org/meditation-for-students/',
        'author' => 'Dr. Maria Santos',
        'date' => '2023-12-20',
        'category' => 'mindfulness',
        'image_url' => 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 7,
        'featured' => 0
    ],
    [
        'title' => 'Coping with Depression: A Student\'s Guide',
        'summary' => 'Understanding depression in college students, recognizing symptoms, and learning effective coping strategies and when to seek help.',
        'link' => 'https://www.adaa.org/understanding-anxiety/depression',
        'author' => 'Dr. James Wilson',
        'date' => '2023-12-18',
        'category' => 'depression',
        'image_url' => 'https://images.unsplash.com/photo-1559757175-0eb30cd8c063?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 8,
        'featured' => 0
    ],
    [
        'title' => 'Time Management for Mental Health',
        'summary' => 'Discover how effective time management can reduce stress and improve your mental well-being while maintaining academic success.',
        'link' => 'https://www.verywellmind.com/time-management-tips-for-students-3145159',
        'author' => 'Dr. Amanda Foster',
        'date' => '2023-12-12',
        'category' => 'academic',
        'image_url' => 'https://images.unsplash.com/photo-1611224923853-80b023f02d71?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 6,
        'featured' => 0
    ],
    [
        'title' => 'Therapy and Counseling: What to Expect',
        'summary' => 'A comprehensive guide to different types of therapy, what to expect in counseling sessions, and how to find the right therapist for you.',
        'link' => 'https://www.apa.org/topics/psychotherapy',
        'author' => 'Dr. Patricia Lee',
        'date' => '2023-12-01',
        'category' => 'therapy',
        'image_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 9,
        'featured' => 0
    ],
    [
        'title' => 'Building Resilience in Challenging Times',
        'summary' => 'Learn how to develop emotional resilience and bounce back from setbacks, failures, and difficult experiences as a student.',
        'link' => 'https://www.apa.org/topics/resilience',
        'author' => 'Dr. Carlos Mendez',
        'date' => '2023-11-25',
        'category' => 'coping',
        'image_url' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 7,
        'featured' => 0
    ],
    [
        'title' => 'Social Media and Mental Health: Finding Balance',
        'summary' => 'Explore the impact of social media on mental health and learn strategies for healthy social media use as a student.',
        'link' => 'https://www.psychologytoday.com/us/blog/mental-wealth/201412/social-media-and-mental-health',
        'author' => 'Dr. Rachel Green',
        'date' => '2023-11-20',
        'category' => 'self-care',
        'image_url' => 'https://images.unsplash.com/photo-1611224923853-80b023f02d71?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 5,
        'featured' => 0
    ],
    [
        'title' => 'Managing Test Anxiety: Strategies That Work',
        'summary' => 'Comprehensive strategies for overcoming test anxiety, including preparation techniques, relaxation methods, and mindset shifts.',
        'link' => 'https://www.adaa.org/understanding-anxiety/specific-phobias/test-anxiety',
        'author' => 'Dr. Kevin Park',
        'date' => '2023-11-15',
        'category' => 'anxiety',
        'image_url' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 6,
        'featured' => 0
    ],
    [
        'title' => 'The Importance of Physical Activity for Mental Health',
        'summary' => 'Discover how regular exercise can improve your mood, reduce stress, and enhance your overall mental well-being as a student.',
        'link' => 'https://www.apa.org/topics/exercise-fitness/stress',
        'author' => 'Dr. Michelle Torres',
        'date' => '2023-11-10',
        'category' => 'self-care',
        'image_url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 8,
        'featured' => 0
    ],
    [
        'title' => 'Financial Stress and Mental Health: A Student\'s Guide',
        'summary' => 'Learn how to manage financial stress as a student and protect your mental health while dealing with money concerns.',
        'link' => 'https://www.apa.org/topics/money/mental-health',
        'author' => 'Dr. David Kim',
        'date' => '2023-11-05',
        'category' => 'stress',
        'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 7,
        'featured' => 0
    ],
    [
        'title' => 'Sleep Hygiene for Better Mental Health',
        'summary' => 'Learn essential sleep hygiene practices that can significantly improve your mental health, focus, and academic performance.',
        'link' => 'https://www.sleepfoundation.org/sleep-hygiene',
        'author' => 'Dr. Sarah Johnson',
        'date' => '2023-10-30',
        'category' => 'sleep',
        'image_url' => '',
        'read_time' => 6,
        'featured' => 0
    ],
    [
        'title' => 'Digital Detox: Reclaiming Your Mental Space',
        'summary' => 'Discover the benefits of taking breaks from technology and learn practical strategies for a healthy digital lifestyle.',
        'link' => 'https://www.psychologytoday.com/us/blog/mental-wealth/201402/digital-detox',
        'author' => 'Dr. Alex Chen',
        'date' => '2023-10-25',
        'category' => 'self-care',
        'image_url' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 5,
        'featured' => 0
    ],
    [
        'title' => 'Building Healthy Study Habits',
        'summary' => 'Learn how to create sustainable study routines that promote both academic success and mental well-being.',
        'link' => 'https://www.verywellmind.com/study-habits-3145159',
        'author' => 'Dr. Michael Rodriguez',
        'date' => '2023-10-20',
        'category' => 'academic',
        'image_url' => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
        'read_time' => 7,
        'featured' => 0
    ]
];

// Check if articles already exist to avoid duplicates
$existing_titles = [];
$result = $conn->query("SELECT title FROM articles");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existing_titles[] = strtolower($row['title']);
    }
}

$added_count = 0;
$skipped_count = 0;

foreach ($new_articles as $article) {
    // Check if article already exists
    if (in_array(strtolower($article['title']), $existing_titles)) {
        echo "<p style='color: orange;'>⚠️ Skipped: '{$article['title']}' (already exists)</p>";
        $skipped_count++;
        continue;
    }
    
    // Insert new article (without content column)
    $sql = "INSERT INTO articles (title, summary, link, author, date, category, image_url, read_time, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("sssssssii", 
            $article['title'],
            $article['summary'], 
            $article['link'],
            $article['author'],
            $article['date'],
            $article['category'],
            $article['image_url'],
            $article['read_time'],
            $article['featured']
        );
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Added: '{$article['title']}'</p>";
            $added_count++;
        } else {
            echo "<p style='color: red;'>✗ Error adding '{$article['title']}': " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>✗ Error preparing statement for '{$article['title']}': " . $conn->error . "</p>";
    }
}

echo "<h3>Summary</h3>";
echo "<p><strong>Articles added:</strong> $added_count</p>";
echo "<p><strong>Articles skipped (already exist):</strong> $skipped_count</p>";

if ($added_count > 0) {
    echo "<p style='color: green; font-weight: bold;'>✅ Successfully added $added_count new articles!</p>";
    echo "<p><a href='articles.php'>View All Articles</a></p>";
} else {
    echo "<p style='color: orange; font-weight: bold;'>ℹ️ No new articles were added (all may already exist).</p>";
}

$conn->close();
?>
