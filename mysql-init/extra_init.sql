-- Extra tables and sample data for listBookingsv2.php
-- Run this against the 'aarna' database used by connect_local.php

CREATE TABLE IF NOT EXISTS ChargeList (
  ChargeNo INT AUTO_INCREMENT PRIMARY KEY,
  ChargeName VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Charges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ChargeNo INT NOT NULL,
  eventID INT NOT NULL,
  Amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  comments TEXT,
  FOREIGN KEY (ChargeNo) REFERENCES ChargeList(ChargeNo) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Payments (
  PaymentID INT AUTO_INCREMENT PRIMARY KEY,
  eventID INT NOT NULL,
  Amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  PaidOn DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS PaymentDueDates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eventID INT NOT NULL,
  Amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  DueDate DATE,
  paidFlag CHAR(1) DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS DecorDetails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eventID INT NOT NULL,
  nxtDecDate DATE,
  decFinSts VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hist_Bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eventID INT NOT NULL,
  changedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  diff TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed ChargeList
INSERT INTO ChargeList (ChargeName) VALUES
('Rent'),('Security Deposit'),('Decoration'),('Sound'),('Lighting');

-- Add sample charges for eventIDs 1 and 2 (if they exist)
INSERT INTO Charges (ChargeNo, eventID, Amount, comments) VALUES
(3, 1, 15000.00, 'Decor package A'),
(4, 1, 5000.00, 'Sound system'),
(3, 2, 12000.00, 'Decor package B');

-- Sample payments
INSERT INTO Payments (eventID, Amount, PaidOn) VALUES
(1, 5000.00, NOW()),
(2, 4000.00, NOW());

-- Payment due dates
INSERT INTO PaymentDueDates (eventID, Amount, DueDate, paidFlag) VALUES
(1, 10000.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'N'),
(2, 8000.00, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'N');

-- Decor details
INSERT INTO DecorDetails (eventID, nxtDecDate, decFinSts) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'NotFinalized'),
(2, DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'Finalized');

-- sample history
INSERT INTO hist_Bookings (eventID, diff) VALUES
(1, 'Initial booking'),
(2, 'Added extra pax');

-- End of extra init
