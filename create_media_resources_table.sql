-- Create media_resources table
CREATE TABLE IF NOT EXISTS media_resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('videos', 'podcasts', 'books', 'apps', 'interactive', 'websites') NOT NULL,
    media_type VARCHAR(50) NOT NULL,
    thumbnail_url VARCHAR(500),
    external_url VARCHAR(500) NOT NULL,
    duration VARCHAR(20),
    author VARCHAR(100),
    source VARCHAR(100),
    platform VARCHAR(50),
    rating DECIMAL(2,1),
    year INT,
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert video resources
INSERT INTO media_resources (title, description, category, media_type, thumbnail_url, external_url, duration, source, featured) VALUES
('Understanding Anxiety in College Students', 'A comprehensive guide to recognizing and managing anxiety in academic settings. Learn practical strategies for dealing with academic pressure and social anxiety.', 'videos', 'Educational Video', 'https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://www.youtube.com/watch?v=example1', '15:30', 'Psychology Department', TRUE),
('Effective Stress Management Techniques', 'Learn practical techniques to manage academic stress and maintain mental health. Includes breathing exercises, time management, and relaxation methods.', 'videos', 'Educational Video', 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://www.youtube.com/watch?v=example2', '20:15', 'Wellness Center', FALSE),
('Mindfulness Meditation for Students', 'Guided meditation sessions specifically designed for college students to reduce stress and improve focus. Perfect for study breaks and exam preparation.', 'videos', 'Meditation Video', 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://www.youtube.com/watch?v=example3', '10:00', 'Mindfulness Center', TRUE),
('Building Resilience in College', 'Learn how to bounce back from setbacks and build mental strength for academic and personal challenges. Essential skills for student success.', 'videos', 'Educational Video', 'https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://www.youtube.com/watch?v=example4', '25:45', 'Student Counseling', FALSE),
('Sleep Hygiene for Students', 'Essential tips and techniques for improving sleep quality and establishing healthy sleep routines during college years.', 'videos', 'Educational Video', 'https://images.unsplash.com/photo-1541781774459-671136601c8f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://www.youtube.com/watch?v=example5', '12:30', 'Sleep Research Center', FALSE),
('Managing Social Anxiety', 'Practical strategies for overcoming social anxiety in academic and social settings. Build confidence and improve social interactions.', 'videos', 'Educational Video', 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://www.youtube.com/watch?v=example6', '18:20', 'Social Psychology Lab', FALSE);

-- Insert podcast resources
INSERT INTO media_resources (title, description, category, media_type, thumbnail_url, external_url, duration, source, featured) VALUES
('Mental Health Matters: Student Edition', 'Weekly discussions about mental health challenges faced by college students. Real stories, expert advice, and practical solutions.', 'podcasts', 'Educational Podcast', 'https://images.unsplash.com/photo-1478737270239-2f02b77fc618?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://podcasts.apple.com/example1', '45 min', 'Student Wellness', TRUE),
('Study Smart: Mental Health Edition', 'Tips and strategies for maintaining mental health while studying effectively. Balance academic success with personal well-being.', 'podcasts', 'Educational Podcast', 'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://spotify.com/example2', '35 min', 'Academic Support', FALSE),
('The Happiness Lab with Dr. Laurie Santos', 'Science-based insights into happiness and well-being from Yale University\'s most popular course. Evidence-based strategies for a happier life.', 'podcasts', 'Educational Podcast', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://podcasts.apple.com/example3', '30-45 min', 'Yale University', TRUE),
('Therapy for Black Girls', 'Mental health conversations and resources specifically for Black women and girls. Breaking down barriers and providing culturally relevant support.', 'podcasts', 'Educational Podcast', 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://therapyforblackgirls.com', '45-60 min', 'Dr. Joy Harden Bradford', FALSE),
('The Mental Health Podcast', 'Comprehensive discussions on various mental health topics, featuring interviews with mental health professionals and personal stories.', 'podcasts', 'Educational Podcast', 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://spotify.com/example5', '40-50 min', 'Mental Health Foundation', FALSE),
('Mindful University', 'Meditation and mindfulness practices specifically designed for university students. Short, practical sessions for busy student life.', 'podcasts', 'Meditation Podcast', 'https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://podcasts.apple.com/example6', '15-20 min', 'Mindfulness Institute', FALSE);

-- Insert book resources
INSERT INTO media_resources (title, description, category, media_type, thumbnail_url, external_url, author, year, featured) VALUES
('The Mindful Student: A Guide to Academic Success', 'Practical strategies for maintaining mental well-being while excelling in studies. Evidence-based techniques for stress management and academic performance.', 'books', 'Educational Book', 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://amazon.com/example1', 'Dr. Sarah Johnson', 2023, TRUE),
('The Anxiety Toolkit: Strategies for Managing Anxiety', 'Evidence-based techniques for managing anxiety and panic attacks in daily life. Practical tools for immediate relief and long-term management.', 'books', 'Self-Help Book', 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://amazon.com/example2', 'Dr. Alice Boyes', 2022, FALSE),
('Digital Wellness: Balancing Technology and Mental Health', 'A modern guide to maintaining mental health in our digital age. Strategies for healthy technology use and digital detox.', 'books', 'Self-Help Book', 'https://images.unsplash.com/photo-1516321318423-f06f85b504dc?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://amazon.com/example3', 'Dr. Tanya Goodin', 2023, FALSE),
('The Stress-Proof Brain: Master Your Emotional Response to Stress', 'Neuroscience-based strategies for building resilience and managing stress effectively. Transform your relationship with stress.', 'books', 'Educational Book', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://amazon.com/example4', 'Dr. Melanie Greenberg', 2021, TRUE),
('Mindfulness for Students: A Practical Guide', 'Step-by-step mindfulness practices designed specifically for students. Improve focus, reduce anxiety, and enhance academic performance.', 'books', 'Self-Help Book', 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://amazon.com/example5', 'Dr. Mark Williams', 2022, FALSE),
('The Sleep Solution: Why Your Sleep is Broken and How to Fix It', 'Comprehensive guide to understanding and improving sleep quality. Essential for students struggling with sleep issues.', 'books', 'Educational Book', 'https://images.unsplash.com/photo-1541781774459-671136601c8f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://amazon.com/example6', 'Dr. W. Chris Winter', 2021, FALSE);

-- Insert app resources
INSERT INTO media_resources (title, description, category, media_type, thumbnail_url, external_url, platform, rating, featured) VALUES
('Study Balance: Academic Wellness', 'An app designed to help students maintain a healthy balance between studies and well-being. Track study time, breaks, and mental health metrics.', 'apps', 'Wellness App', 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://play.google.com/example1', 'iOS & Android', 4.8, TRUE),
('Headspace: Meditation & Sleep', 'Guided meditation and mindfulness exercises to help with stress, anxiety, and sleep. Perfect for students needing quick stress relief.', 'apps', 'Meditation App', 'https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://headspace.com', 'iOS & Android', 4.9, TRUE),
('Mood Meter: Emotional Intelligence', 'Track your emotions and learn to regulate them for better mental health and relationships. Build emotional awareness and regulation skills.', 'apps', 'Mood Tracking App', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://moodmeterapp.com', 'iOS & Android', 4.7, FALSE),
('Calm: Sleep, Meditate, Relax', 'Comprehensive wellness app with meditation, sleep stories, and relaxation techniques. Great for stress management and better sleep.', 'apps', 'Wellness App', 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://calm.com', 'iOS & Android', 4.8, FALSE),
('Forest: Stay Focused', 'Beat phone addiction and stay focused on your studies. Plant virtual trees while staying away from distracting apps.', 'apps', 'Productivity App', 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://forestapp.cc', 'iOS & Android', 4.6, FALSE),
('Daylio: Mood & Micro Diary', 'Simple mood tracking and micro-journaling app. Track your daily mood, activities, and identify patterns in your mental health.', 'apps', 'Mood Tracking App', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://daylio.net', 'iOS & Android', 4.5, FALSE);

-- Insert interactive resources
INSERT INTO media_resources (title, description, category, media_type, thumbnail_url, external_url, duration, source, featured) VALUES
('Interactive Breathing Exercises', 'Guided breathing exercises with visual cues to help manage anxiety and stress in real-time. Immediate relief when you need it most.', 'interactive', 'Breathing Tool', 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://mindmatters.com/breathing', '5-15 min', 'Mind Matters', TRUE),
('Mood Tracking & Journaling', 'Interactive tools to track your mood, thoughts, and progress over time. Identify patterns and triggers in your mental health journey.', 'interactive', 'Tracking Tool', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://mindmatters.com/mood-tracking', 'Daily', 'Mind Matters', FALSE),
('Stress Assessment Tool', 'Comprehensive stress assessment to help identify your stress levels and get personalized recommendations for stress management.', 'interactive', 'Assessment Tool', 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://mindmatters.com/stress-assessment', '10-15 min', 'Mind Matters', FALSE),
('Study Break Generator', 'Personalized study break suggestions based on your current stress level and available time. Quick activities to refresh your mind.', 'interactive', 'Wellness Tool', 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://mindmatters.com/study-breaks', '5-30 min', 'Mind Matters', FALSE),
('Sleep Hygiene Planner', 'Interactive tool to help you create and maintain healthy sleep habits. Track your sleep patterns and get personalized recommendations.', 'interactive', 'Sleep Tool', 'https://images.unsplash.com/photo-1541781774459-671136601c8f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://mindmatters.com/sleep-planner', 'Daily', 'Mind Matters', FALSE),
('Crisis Support Chatbot', 'AI-powered chatbot providing immediate mental health support and crisis intervention. Available 24/7 for urgent mental health needs.', 'interactive', 'Support Tool', 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://mindmatters.com/crisis-chat', '24/7', 'Mind Matters', TRUE);

-- Insert website resources
INSERT INTO media_resources (title, description, category, media_type, thumbnail_url, external_url, source, featured) VALUES
('National Institute of Mental Health', 'Comprehensive mental health information, research, and resources from the leading federal agency for mental health research.', 'websites', 'Government Resource', 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://nimh.nih.gov', 'NIMH', TRUE),
('American Psychological Association', 'Professional psychology resources, mental health information, and finding a psychologist. Evidence-based mental health guidance.', 'websites', 'Professional Resource', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://apa.org', 'APA', TRUE),
('Mental Health America', 'Mental health advocacy, resources, and screening tools. Comprehensive support for mental health awareness and treatment.', 'websites', 'Advocacy Resource', 'https://images.unsplash.com/photo-1506905925346-14bda5d4c69d?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://mhanational.org', 'MHA', FALSE),
('Crisis Text Line', '24/7 crisis support via text message. Free, confidential support for anyone in crisis. Text HOME to 741741.', 'websites', 'Crisis Resource', 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://crisistextline.org', 'Crisis Text Line', TRUE),
('Student Mental Health Resources', 'Comprehensive mental health resources specifically designed for college students. Campus-specific support and guidance.', 'websites', 'Student Resource', 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://studentmentalhealth.org', 'Student Mental Health', FALSE),
('Mindfulness-Based Stress Reduction', 'Official MBSR resources and information. Learn about mindfulness-based approaches to stress reduction and mental health.', 'websites', 'Educational Resource', 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80', 'https://umassmed.edu/cfm/mindfulness-based-programs', 'UMass Medical School', FALSE);














