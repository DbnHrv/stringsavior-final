-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2025 at 09:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stringsavior_1`
--

-- --------------------------------------------------------

--
-- Table structure for table `musical_instrument_inventory`
--

CREATE TABLE `musical_instrument_inventory` (
  `InventoryID` varchar(20) NOT NULL,
  `ProductID` varchar(20) DEFAULT NULL,
  `SupplierID` varchar(20) DEFAULT NULL,
  `UnitID` int(11) DEFAULT NULL,
  `Qty` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `musical_instrument_inventory`
--

INSERT INTO `musical_instrument_inventory` (`InventoryID`, `ProductID`, `SupplierID`, `UnitID`, `Qty`) VALUES
('INV01', 'PC01', 'SUP01', 1, 1),
('INV02', 'PC02', 'SUP01', 1, 3),
('INV03', 'PC03', 'SUP04', 1, 1),
('INV04', 'PC07', 'SUP05', 4, 2),
('INV05', 'PC08', 'SUP02', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `music_inventory_detail`
--

CREATE TABLE `music_inventory_detail` (
  `InventoryDetailID` varchar(20) NOT NULL,
  `InventoryID` varchar(20) DEFAULT NULL,
  `ProductID` varchar(20) DEFAULT NULL,
  `SupplierID` varchar(20) DEFAULT NULL,
  `UnitID` int(11) DEFAULT NULL,
  `Qty` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `music_inventory_detail`
--

INSERT INTO `music_inventory_detail` (`InventoryDetailID`, `InventoryID`, `ProductID`, `SupplierID`, `UnitID`, `Qty`) VALUES
('InvD01', 'INV01', 'PC01', 'SUP01', 1, 1),
('InvD02', 'INV02', 'PC02', 'SUP01', 1, 3),
('InvD03', 'INV03', 'PC03', 'SUP04', 1, 1),
('InvD04', 'INV04', 'PC07', 'SUP05', 4, 2),
('InvD05', 'INV05', 'PC08', 'SUP02', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `OrderID` varchar(20) NOT NULL,
  `InventoryID` varchar(20) DEFAULT NULL,
  `Qty` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`OrderID`, `InventoryID`, `Qty`) VALUES
('ORD01', 'INV01', 1),
('ORD02', 'INV02', 3),
('ORD03', 'INV03', 1),
('ORD04', 'INV04', 2),
('ORD05', 'INV05', 1);

-- --------------------------------------------------------

--
-- Table structure for table `order_detail`
--

CREATE TABLE `order_detail` (
  `OrderDetailID` varchar(20) NOT NULL,
  `OrderID` varchar(20) DEFAULT NULL,
  `ProductID` varchar(20) DEFAULT NULL,
  `UnitID` int(11) DEFAULT NULL,
  `Qty` int(11) DEFAULT NULL,
  `DeclaredValue` decimal(10,2) DEFAULT NULL,
  `TotalPrice` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_detail`
--

INSERT INTO `order_detail` (`OrderDetailID`, `OrderID`, `ProductID`, `UnitID`, `Qty`, `DeclaredValue`, `TotalPrice`) VALUES
('ORDD01', 'ORD01', 'PC01', 1, 1, 8500.00, 8500.00),
('ORDD02', 'ORD02', 'PC02', 1, 3, 9200.00, 27600.00),
('ORDD03', 'ORD03', 'PC03', 1, 1, 6800.00, 6800.00),
('ORDD04', 'ORD04', 'PC07', 4, 2, 120.00, 240.00),
('ORDD05', 'ORD05', 'PC08', 1, 1, 450.00, 450.00);

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `ProductID` varchar(20) NOT NULL,
  `PCodeID` varchar(20) DEFAULT NULL,
  `PBrandID` varchar(20) DEFAULT NULL,
  `PModelID` varchar(20) DEFAULT NULL,
  `Description` varchar(200) DEFAULT NULL,
  `ImagePath` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`ProductID`, `PCodeID`, `PBrandID`, `PModelID`, `Description`, `ImagePath`) VALUES
('P101', 'PC01', 'FMT01', 'M01', 'Electric Guitar', NULL),
('P102', 'PC02', 'FMT01', 'M02', 'Electric Guitar', NULL),
('P103', 'PC03', 'FND21', 'M04', 'Bass Guitar', NULL),
('P104', 'PC04', 'FND18', 'P04', 'Acoustic-Electric Guitar', NULL),
('P105', 'PC05', NULL, 'M05', 'Guitar Strings Set', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_brand`
--

CREATE TABLE `product_brand` (
  `PBrandID` varchar(20) NOT NULL,
  `BrandName` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_brand`
--

INSERT INTO `product_brand` (`PBrandID`, `BrandName`) VALUES
('FMT01', 'Fender'),
('FND21', 'Fender Deluxe'),
('GIB10', 'Gibson'),
('IBZ11', 'Ibanez'),
('P04', 'Yamaha');

-- --------------------------------------------------------

--
-- Table structure for table `product_code`
--

CREATE TABLE `product_code` (
  `PCodeID` varchar(20) NOT NULL,
  `Code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_code`
--

INSERT INTO `product_code` (`PCodeID`, `Code`) VALUES
('PC01', 'GTR01'),
('PC02', 'GTR02'),
('PC03', 'GTR03'),
('PC04', 'GTR04'),
('PC05', 'STP02'),
('PC06', 'GTR05'),
('PC07', 'ACST01'),
('PC08', 'STRNG01');

-- --------------------------------------------------------

--
-- Table structure for table `product_model`
--

CREATE TABLE `product_model` (
  `PModelID` varchar(20) NOT NULL,
  `ModelName` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_model`
--

INSERT INTO `product_model` (`PModelID`, `ModelName`) VALUES
('M01', 'Stratocaster'),
('M02', 'Telecaster'),
('M03', 'Acoustic Deluxe'),
('M04', 'Electric Pro'),
('M05', 'Guitar Strings Set');

-- --------------------------------------------------------

--
-- Table structure for table `product_price`
--

CREATE TABLE `product_price` (
  `PriceID` varchar(20) NOT NULL,
  `ProductID` varchar(20) DEFAULT NULL,
  `Date` date DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_price`
--

INSERT INTO `product_price` (`PriceID`, `ProductID`, `Date`, `Price`) VALUES
('11101', 'PC01', '2025-01-05', 8500.00),
('11102', 'PC05', '2025-02-21', 350.00),
('11103', 'PC06', '2025-03-12', 7200.00),
('11104', 'PC07', '2025-05-15', 120.00),
('11105', 'PC08', '2025-07-11', 450.00),
('PR1763534349948', 'P104', '2025-11-19', 211.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_report`
--

CREATE TABLE `sale_report` (
  `SaleID` varchar(20) NOT NULL,
  `ProductID` varchar(20) DEFAULT NULL,
  `Qty` int(11) DEFAULT NULL,
  `UnitID` int(11) DEFAULT NULL,
  `Date` date DEFAULT NULL,
  `TotalSale` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_report`
--

INSERT INTO `sale_report` (`SaleID`, `ProductID`, `Qty`, `UnitID`, `Date`, `TotalSale`) VALUES
('SALE01', 'PC01', 1, 1, '2025-01-15', 8500.00),
('SALE02', 'PC02', 2, 1, '2025-02-20', 18400.00),
('SALE03', 'PC05', 1, 1, '2025-03-05', 350.00),
('SALE04', 'PC07', 5, 4, '2025-05-22', 600.00),
('SALE05', 'PC08', 1, 1, '2025-07-15', 450.00);

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `SupplierID` varchar(20) NOT NULL,
  `SupplierName` varchar(100) DEFAULT NULL,
  `SupplierContact` varchar(50) DEFAULT NULL,
  `SupplierAddress` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`SupplierID`, `SupplierName`, `SupplierContact`, `SupplierAddress`) VALUES
('SUP01', 'Yamaha Distributor', '09171234567', 'Quezon City'),
('SUP02', 'Fender Supplier', '09181234567', 'Manila'),
('SUP03', 'Gibson Importer', '09191234567', 'Cebu'),
('SUP04', 'Ibanez Reseller', '09201234567', 'Davao'),
('SUP05', 'String Masters Co.', '09211234567', 'Bacolod');

-- --------------------------------------------------------

--
-- Table structure for table `unit_of_measurement`
--

CREATE TABLE `unit_of_measurement` (
  `UnitID` int(11) NOT NULL,
  `MetricUnit` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit_of_measurement`
--

INSERT INTO `unit_of_measurement` (`UnitID`, `MetricUnit`) VALUES
(1, 'Pieces'),
(2, 'Set'),
(3, 'Bundle'),
(4, 'Pack'),
(5, 'Dozen');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `street` varchar(150) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `user_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `first_name`, `middle_name`, `last_name`, `age`, `gender`, `birthdate`, `street`, `barangay`, `city`, `province`, `contact`, `email`, `password`, `user_type`, `created_at`) VALUES
(1, 'Harvey', 'Isugan', 'Doblon', 21, 'Male', '2004-01-06', 'Purok 1', 'Dumoy', 'Davao City', 'Davao Del Sur', '09515733358', 'doblonharvey12@gmail.com', '$2y$10$z5iT5T1d9kT2eSmabySNAuz9kVnla.GnSImvoB./uGD1E2VC.vlZ.', 'Store Owner', '2025-11-18 20:10:14'),
(4, 'Dave', 'V', 'Dela Cerna', 21, 'Male', '2222-02-22', 'Purok 12', 'Baliok', 'Davao City', 'Davao Del Sur', '00991335526', 'dave@gmail.com', '$2y$10$uL8cvFEKaiN.dyh8L1D7We0JJ7Oml0bXQOAmucOwVDiIXaDTKRem6', 'Supplier', '2025-11-18 23:31:26');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `musical_instrument_inventory`
--
ALTER TABLE `musical_instrument_inventory`
  ADD PRIMARY KEY (`InventoryID`);

--
-- Indexes for table `music_inventory_detail`
--
ALTER TABLE `music_inventory_detail`
  ADD PRIMARY KEY (`InventoryDetailID`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`OrderID`);

--
-- Indexes for table `order_detail`
--
ALTER TABLE `order_detail`
  ADD PRIMARY KEY (`OrderDetailID`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`ProductID`);

--
-- Indexes for table `product_brand`
--
ALTER TABLE `product_brand`
  ADD PRIMARY KEY (`PBrandID`);

--
-- Indexes for table `product_code`
--
ALTER TABLE `product_code`
  ADD PRIMARY KEY (`PCodeID`);

--
-- Indexes for table `product_model`
--
ALTER TABLE `product_model`
  ADD PRIMARY KEY (`PModelID`);

--
-- Indexes for table `product_price`
--
ALTER TABLE `product_price`
  ADD PRIMARY KEY (`PriceID`);

--
-- Indexes for table `sale_report`
--
ALTER TABLE `sale_report`
  ADD PRIMARY KEY (`SaleID`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`SupplierID`);

--
-- Indexes for table `unit_of_measurement`
--
ALTER TABLE `unit_of_measurement`
  ADD PRIMARY KEY (`UnitID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
