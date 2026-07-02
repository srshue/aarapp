use aarna;

CREATE TABLE IF NOT EXISTS Bookings (
  EventID INT AUTO_INCREMENT PRIMARY KEY,
  EventDate DATETIME,
  end DATETIME,
  Name VARCHAR(255),
  hallLocation VARCHAR(255),
  comments TEXT
);

CREATE TABLE IF NOT EXISTS Employees (
  EmpID INT AUTO_INCREMENT PRIMARY KEY,
  Name VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS FunctionDetails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eventID INT NOT NULL,
  data LONGTEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- sample employees
INSERT INTO Employees (Name) VALUES ('Ravi'),('Suresh'),('Anita'),('Priya'),('Kumar'),('Deepa');

-- sample bookings
INSERT INTO Bookings (EventDate, end, Name, hallLocation, comments) VALUES
  (NOW() + INTERVAL 2 DAY, NOW() + INTERVAL 2 DAY + INTERVAL 4 HOUR, 'Test Client A', 'partyHall', 'Sample A'),
  (NOW() + INTERVAL 5 DAY, NOW() + INTERVAL 5 DAY + INTERVAL 5 HOUR, 'Test Client B', 'Mahal', 'Sample B');
