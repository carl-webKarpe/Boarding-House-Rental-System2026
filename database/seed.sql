-- =====================================================================
-- Sample data for local development. Run AFTER database/schema.sql.
--
-- Test accounts (change these passwords before real use):
--   Admin     admin@example.com      Admin@12345
--   Landlord  landlord@example.com   Landlord@123   (approved)
--   Landlord  renato@example.com     Landlord@123   (approved)
--   Landlord  pending@example.com    Landlord@123   (waiting for admin approval)
--   Tenant    tenant@example.com     Tenant@1234
-- =====================================================================
USE bhsystem;

INSERT INTO users (id, username, email, password_hash, role, first_name, last_name, phone, approval_status) VALUES
  (1, 'superadmin', 'admin@example.com',    '$2y$12$wdO47/zG3i0FUcFuhdGEYOtl6oS78tKT283Bms7Vz3pcyX9/bQCtS', 'super_admin', 'System', 'Administrator', NULL, 'approved'),
  (2, 'mariasantos', 'landlord@example.com', '$2y$12$GsESnNmg0sN/GAZ4B8QDyOAmeRUkEUtKeKlsekRC4xY6mb3ulQHu2', 'landlord', 'Maria', 'Santos', '09171234567', 'approved'),
  (3, 'renatocruz', 'renato@example.com',    '$2y$12$GsESnNmg0sN/GAZ4B8QDyOAmeRUkEUtKeKlsekRC4xY6mb3ulQHu2', 'landlord', 'Renato', 'Cruz', '09186542211', 'approved'),
  (4, 'lizatorres', 'pending@example.com',   '$2y$12$GsESnNmg0sN/GAZ4B8QDyOAmeRUkEUtKeKlsekRC4xY6mb3ulQHu2', 'landlord', 'Liza', 'Torres', '09991123344', 'pending'),
  (5, 'juandelacruz', 'tenant@example.com',  '$2y$12$NX0p3/JbUKEeRiHeDh5.5ubGik2S/AoXSBoDrOcmlIxtAO31cObym', 'tenant', 'Juan', 'Dela Cruz', '09123456789', 'approved');

INSERT INTO landlord_profiles (user_id, property_name, business_address, barangay, municipality, province) VALUES
  (2, 'Green View Boarding House', 'Purok 2', 'Purok 2', 'Dapa', 'Surigao del Norte'),
  (3, 'Northview Student Residences', 'Brgy. Osmeña', 'Brgy. Osmeña', 'Dapa', 'Surigao del Norte'),
  (4, 'Torres Dormitory', 'Brgy. Union', 'Brgy. Union', 'Dapa', 'Surigao del Norte');

-- Coordinates are around Siargao Island Institute of Technology (SIIT), Dapa.
INSERT INTO boarding_houses (id, landlord_id, name, description, address, barangay, city, province, nearby_school, distance_to_school, latitude, longitude, gender_policy, house_rules, contact_phone) VALUES
  (1, 2, 'Green View Boarding House',
   'Quiet, well-kept boarding house a short walk from SIIT with a shared study area and reliable Wi-Fi.',
   'Purok 2, National Highway', 'Purok 2', 'Dapa', 'Surigao del Norte', 'SIIT', '210 m', 9.760800, 126.049000, 'any',
   'No smoking\nQuiet hours from 10 PM to 6 AM\nVisitors allowed until 8 PM\nRent due every 5th of the month', '09171234567'),
  (2, 2, 'Island Home Boarding House',
   'Friendly home-style boarding house with a shared kitchen and laundry area.',
   'Brgy. 5', 'Brgy. 5', 'Dapa', 'Surigao del Norte', 'SIIT', '270 m', 9.760200, 126.049500, 'any',
   'No pets\nKeep the kitchen clean after use\nGuests until 8 PM', '09171234567'),
  (3, 3, 'Student Haven',
   'Private rooms with their own bathroom, ideal for students who need a quiet place to focus.',
   'Brgy. 9', 'Brgy. 9', 'Dapa', 'Surigao del Norte', 'SIIT', '240 m', 9.761200, 126.049300, 'any',
   'No smoking\nNo loud music after 9 PM\nRent due on the first week of each month', '09186542211'),
  (4, 3, 'Northview Student Residences',
   'Affordable shared rooms with a guarded entrance and a common study area.',
   'Brgy. Osmeña', 'Brgy. Osmeña', 'Dapa', 'Surigao del Norte', 'SIIT', '330 m', 9.761800, 126.050000, 'female',
   'Female tenants only\nCurfew at 10 PM\nNo visitors during exam week', '09186542211'),
  (5, 3, 'Boarding House Sunrise',
   'Budget-friendly bedspace close to SIIT with a shared kitchen.',
   'Brgy. 3', 'Brgy. 3', 'Dapa', 'Surigao del Norte', 'SIIT', '250 m', 9.759800, 126.049200, 'male',
   'Male tenants only\nCurfew at 10 PM\nMonthly dues every 10th', '09186542211'),
  (6, 2, 'Seaside Boarders',
   'Simple, clean bedspace near the coast with water included.',
   'Brgy. Union', 'Brgy. Union', 'Dapa', 'Surigao del Norte', 'SIIT', '320 m', 9.760500, 126.050000, 'any',
   'No smoking\nVisitors until 7 PM', '09171234567');

INSERT INTO rooms (id, boarding_house_id, name, room_type, monthly_rent, deposit, advance_payment, capacity, available_slots, size_sqm, amenities, description, status) VALUES
  (1, 1, 'Single Room A', 'solo', 2500, 2500, 2500, 1, 1, 9, 'WiFi,Electric Fan,Bed,Cabinet,Study Table,Shared Bathroom,Electricity Included,Water Included',
   'Private single room with a study table and window.', 'available'),
  (2, 2, 'Shared Room 1', 'shared', 2500, 2500, 2500, 2, 2, 12, 'WiFi,Kitchen,Laundry Area,Bed,Cabinet,Shared Bathroom,Water Included',
   'Shared room for two with access to the kitchen and laundry area.', 'available'),
  (3, 3, 'Private Room with Bathroom', 'solo', 5000, 5000, 5000, 1, 1, 14, 'WiFi,Air Conditioning,Private Bathroom,Bed,Cabinet,Study Table',
   'Air-conditioned private room with its own bathroom.', 'available'),
  (4, 4, 'Shared Room – 2nd Floor', 'shared', 1800, 1800, 1800, 4, 3, 16, 'WiFi,Kitchen,Study Table,CCTV,Shared Bathroom',
   'Four-person shared room with study tables.', 'available'),
  (5, 5, 'Bedspace', 'dormitory', 1300, 1300, 1300, 6, 4, 20, 'WiFi,Kitchen,Electric Fan,Bed,Shared Bathroom',
   'Double-deck bedspace in a six-person room.', 'available'),
  (6, 6, 'Bedspace', 'dormitory', 1200, 1200, 1200, 6, 5, 20, 'WiFi,Kitchen,Electric Fan,Bed,Shared Bathroom,Water Included',
   'Affordable bedspace with water included.', 'available');

INSERT INTO room_photos (room_id, path, sort_order) VALUES
  (1, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1200&q=80', 0),
  (2, 'Image/image2.jpg', 0),
  (3, 'Image/haven.jpg', 0),
  (4, 'Image/image4.jpg', 0),
  (5, 'Image/images.jpg', 0),
  (6, 'Image/image3.jpg', 0);

INSERT INTO bookings (room_id, tenant_id, move_in_date, message, status) VALUES
  (1, 5, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Hi! I am a first-year SIIT student and would like to reserve this room.', 'pending'),
  (2, 5, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Reserving for this semester.', 'approved');

UPDATE rooms SET available_slots = 1 WHERE id = 2;

INSERT INTO reviews (room_id, tenant_id, rating, comment) VALUES
  (2, 5, 5, 'Very clean and close to SIIT. The landlord is responsive.');
