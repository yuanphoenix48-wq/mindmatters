USE mind_matters_db;

-- Create articles table
CREATE TABLE IF NOT EXISTS articles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    content LONGTEXT,
    link VARCHAR(500),
    author VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    category ENUM('stress', 'anxiety', 'academic', 'relationships', 'self-care', 'depression', 'mindfulness', 'sleep', 'therapy', 'coping') DEFAULT 'general',
    image_url VARCHAR(500),
    read_time INT DEFAULT 5,
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample articles with images
INSERT INTO articles (title, summary, content, link, author, date, category, image_url, read_time, featured) VALUES
(
    'Managing Academic Stress: A Complete Guide for Students',
    'Learn evidence-based strategies to manage academic pressure, maintain work-life balance, and thrive in your studies while preserving your mental health.',
    'Academic stress is a common experience among students, but it doesn\'t have to overwhelm you. This comprehensive guide covers practical strategies for managing coursework, exams, and the pressures of student life while maintaining your mental well-being.',
    'https://www.verywellmind.com/managing-academic-stress-3145269',
    'Dr. Sarah Mitchell',
    '2023-12-15',
    'stress',
    'https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    8,
    TRUE
),
(
    'Understanding Anxiety: Signs, Symptoms, and When to Seek Help',
    'Comprehensive guide to recognizing anxiety symptoms, understanding different types of anxiety disorders, and knowing when professional help is needed.',
    'Anxiety affects millions of people worldwide. Learn to identify the signs, understand the different types of anxiety disorders, and discover when it\'s time to seek professional help for yourself or someone you care about.',
    'https://www.nimh.nih.gov/health/topics/anxiety-disorders',
    'Dr. Michael Chen',
    '2023-12-10',
    'anxiety',
    'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    6,
    FALSE
),
(
    'Effective Study Techniques for Better Mental Health',
    'Discover study methods that promote both academic success and mental well-being, including time management and stress-reduction techniques.',
    'The right study techniques can not only improve your academic performance but also protect your mental health. Learn evidence-based methods that help you study effectively while maintaining your well-being.',
    'https://www.apa.org/topics/learning/study-techniques',
    'Dr. Emily Rodriguez',
    '2023-12-08',
    'academic',
    'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    7,
    FALSE
),
(
    'Building Healthy Relationships in College',
    'Learn how to develop meaningful connections, set boundaries, and maintain healthy relationships while navigating college life and mental health challenges.',
    'College is a time of significant social and personal growth. This guide helps you build and maintain healthy relationships while managing the unique challenges of student life.',
    'https://www.psychologytoday.com/us/blog/teen-angst/201401/healthy-relationships-college',
    'Dr. Jennifer Walsh',
    '2023-12-05',
    'relationships',
    'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    5,
    FALSE
),
(
    'The Art of Self-Care: A Student\'s Essential Guide',
    'Practical self-care strategies tailored for students, including physical, emotional, and mental wellness practices that fit into busy academic schedules.',
    'Self-care isn\'t selfish—it\'s essential for your well-being and academic success. Discover practical strategies that fit into your busy student schedule and help you maintain balance.',
    'https://www.mentalhealth.org.uk/explore-mental-health/a-z-topics/self-care',
    'Dr. Lisa Thompson',
    '2023-12-03',
    'self-care',
    'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    9,
    FALSE
),
(
    'Sleep and Mental Health: The Connection You Need to Know',
    'Explore the vital relationship between sleep quality and mental health, with practical tips for improving sleep hygiene and managing sleep-related anxiety.',
    'Quality sleep is fundamental to mental health. Learn about the bidirectional relationship between sleep and mental well-being, and discover practical strategies for better sleep.',
    'https://www.sleepfoundation.org/mental-health',
    'Dr. Robert Kim',
    '2023-11-28',
    'sleep',
    'https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    6,
    FALSE
),
(
    'Mindfulness and Meditation for Students',
    'Learn how mindfulness practices can reduce stress, improve focus, and enhance your overall well-being as a student.',
    'Mindfulness isn\'t just a buzzword—it\'s a powerful tool for managing stress and improving mental clarity. Discover simple meditation techniques that can transform your student experience.',
    'https://www.mindful.org/meditation-for-students/',
    'Dr. Maria Santos',
    '2023-12-20',
    'mindfulness',
    'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    7,
    FALSE
),
(
    'Coping with Depression: A Student\'s Guide',
    'Understanding depression in college students, recognizing symptoms, and learning effective coping strategies and when to seek help.',
    'Depression affects many college students, but it\'s treatable. Learn to recognize the signs, understand the causes, and discover effective coping strategies and treatment options.',
    'https://www.adaa.org/understanding-anxiety/depression',
    'Dr. James Wilson',
    '2023-12-18',
    'depression',
    'https://images.unsplash.com/photo-1559757175-0eb30cd8c063?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    8,
    FALSE
),
(
    'Time Management for Mental Health',
    'Discover how effective time management can reduce stress and improve your mental well-being while maintaining academic success.',
    'Poor time management is a major source of stress for students. Learn practical strategies that help you manage your time effectively while protecting your mental health.',
    'https://www.verywellmind.com/time-management-tips-for-students-3145159',
    'Dr. Amanda Foster',
    '2023-12-12',
    'academic',
    'https://images.unsplash.com/photo-1611224923853-80b023f02d71?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    6,
    FALSE
),
(
    'Therapy and Counseling: What to Expect',
    'A comprehensive guide to different types of therapy, what to expect in counseling sessions, and how to find the right therapist for you.',
    'Therapy can be life-changing, but many students don\'t know what to expect. This guide demystifies the therapy process and helps you find the right mental health professional.',
    'https://www.apa.org/topics/psychotherapy',
    'Dr. Patricia Lee',
    '2023-12-01',
    'therapy',
    'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    9,
    FALSE
),
(
    'Building Resilience in Challenging Times',
    'Learn how to develop emotional resilience and bounce back from setbacks, failures, and difficult experiences as a student.',
    'Resilience is the ability to bounce back from adversity. Discover practical strategies for building emotional strength and developing a resilient mindset that serves you throughout life.',
    'https://www.apa.org/topics/resilience',
    'Dr. Carlos Mendez',
    '2023-11-25',
    'coping',
    'https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    7,
    FALSE
),
(
    'Social Media and Mental Health: Finding Balance',
    'Explore the impact of social media on mental health and learn strategies for healthy social media use as a student.',
    'Social media can both help and harm your mental health. Learn how to use social platforms mindfully and maintain healthy boundaries in the digital age.',
    'https://www.psychologytoday.com/us/blog/mental-wealth/201412/social-media-and-mental-health',
    'Dr. Rachel Green',
    '2023-11-20',
    'self-care',
    'https://images.unsplash.com/photo-1611224923853-80b023f02d71?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    5,
    FALSE
),
(
    'Managing Test Anxiety: Strategies That Work',
    'Comprehensive strategies for overcoming test anxiety, including preparation techniques, relaxation methods, and mindset shifts.',
    'Test anxiety can significantly impact your academic performance. Learn evidence-based strategies for managing anxiety before, during, and after exams.',
    'https://www.adaa.org/understanding-anxiety/specific-phobias/test-anxiety',
    'Dr. Kevin Park',
    '2023-11-15',
    'anxiety',
    'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    6,
    FALSE
),
(
    'The Importance of Physical Activity for Mental Health',
    'Discover how regular exercise can improve your mood, reduce stress, and enhance your overall mental well-being as a student.',
    'Physical activity isn\'t just good for your body—it\'s essential for your mental health. Learn how to incorporate exercise into your student lifestyle for maximum mental health benefits.',
    'https://www.apa.org/topics/exercise-fitness/stress',
    'Dr. Michelle Torres',
    '2023-11-10',
    'self-care',
    'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    8,
    FALSE
),
(
    'Financial Stress and Mental Health: A Student\'s Guide',
    'Learn how to manage financial stress as a student and protect your mental health while dealing with money concerns.',
    'Financial stress is a major concern for many students. Discover practical strategies for managing money stress and maintaining your mental well-being despite financial challenges.',
    'https://www.apa.org/topics/money/mental-health',
    'Dr. David Kim',
    '2023-11-05',
    'stress',
    'https://images.unsplash.com/photo-1554224155-6726b3ff858f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80',
    7,
    FALSE
);

-- Create index for better performance
CREATE INDEX idx_articles_category ON articles(category);
CREATE INDEX idx_articles_date ON articles(date);
CREATE INDEX idx_articles_featured ON articles(featured);














