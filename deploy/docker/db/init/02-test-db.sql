-- Create a separate test database so the PHPUnit suite cannot destroy dev data.
-- Runs after 01-schema.sql (alphabetical order), which has already set up statedecoded.

CREATE DATABASE IF NOT EXISTS `statedecoded_test`
    CHARACTER SET utf8
    COLLATE utf8_general_ci;

GRANT ALL PRIVILEGES ON `statedecoded_test`.* TO 'statedecoded'@'%';
FLUSH PRIVILEGES;

-- Mirror every table from the dev database into the test database
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`api_keys`          LIKE `statedecoded`.`api_keys`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`dictionary`         LIKE `statedecoded`.`dictionary`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`dictionary_general` LIKE `statedecoded`.`dictionary_general`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`editions`           LIKE `statedecoded`.`editions`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`laws`               LIKE `statedecoded`.`laws`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`laws_meta`          LIKE `statedecoded`.`laws_meta`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`laws_references`    LIKE `statedecoded`.`laws_references`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`laws_views`         LIKE `statedecoded`.`laws_views`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`migrations`         LIKE `statedecoded`.`migrations`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`permalinks`         LIKE `statedecoded`.`permalinks`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`settings`           LIKE `statedecoded`.`settings`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`structure`          LIKE `statedecoded`.`structure`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`tags`               LIKE `statedecoded`.`tags`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`text`               LIKE `statedecoded`.`text`;
CREATE TABLE IF NOT EXISTS `statedecoded_test`.`text_sections`      LIKE `statedecoded`.`text_sections`;
