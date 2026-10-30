CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `dislikes` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(50) NOT NULL,
  `commentId` int(11) NOT NULL DEFAULT 0,
  `videoId` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(50) NOT NULL,
  `commentId` int(11) NOT NULL DEFAULT 0,
  `videoId` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `thumbnails` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `videoid` int(11) NOT NULL,
  `filepath` varchar(250) NOT NULL,
  `selected` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `firstName` varchar(25) NOT NULL,
  `lastName` varchar(25) NOT NULL,
  `username` varchar(25) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `singUpDate` datetime NOT NULL DEFAULT current_timestamp(),
  `profilePic` varchar(255) NOT NULL
,
 UNIQUE KEY username_unique (username),
 UNIQUE KEY email_unique (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uploadedBy` varchar(50) NOT NULL,
  `title` varchar(70) NOT NULL,
  `description` varchar(1000) NOT NULL,
  `category` int(11) NOT NULL,
  `privacy` int(11) NOT NULL,
  `filePath` varchar(300) NOT NULL,
  `uploadDate` datetime NOT NULL DEFAULT current_timestamp(),
  `views` int(11) NOT NULL DEFAULT 0,
  `duration` varchar(10) NOT NULL DEFAULT '00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE comments (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, postedBy VARCHAR(50) NOT NULL,
 videoId INT NOT NULL, responseTo INT NOT NULL DEFAULT 0, body TEXT NOT NULL,
 datePosted DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(videoId), INDEX(responseTo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subscribers (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, userTo VARCHAR(50) NOT NULL, userFrom VARCHAR(50) NOT NULL,
 UNIQUE KEY subscription(userTo,userFrom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`id`, `name`) VALUES
(16, 'Film & Animation'),
(17, 'Autos & Vehicals'),
(18, 'Music'),
(19, 'Pets & Animals'),
(20, 'Sports'),
(21, 'Travel & Events'),
(22, 'Gaming'),
(23, 'People & Blogs'),
(24, 'Comedy'),
(25, 'Entertainment'),
(26, 'News & Politics'),
(27, 'Howto & style'),
(28, 'Education'),
(29, 'Science & Technology'),
(30, 'Nonprofits & Activism');
