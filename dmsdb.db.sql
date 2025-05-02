BEGIN TRANSACTION;
DROP TABLE IF EXISTS "Addresses";
CREATE TABLE "Addresses" (
	"ID"	INTEGER,
	"name"	TEXT,
	"code"	TEXT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "Branch";
CREATE TABLE "Branch" (
	"ID"	INTEGER,
	"City"	TEXT,
	"BCode"	TEXT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "Departments";
CREATE TABLE "Departments" (
	"ID"	INTEGER,
	"DeptName"	TEXT,
	"DeptCode"	TEXT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "DocDetails";
CREATE TABLE "DocDetails" (
	"ID"	INTEGER,
	"RefNo"	TEXT,
	"Department"	TEXT,
	"SendTo"	TEXT,
	"Addresse"	TEXT,
	"Signatory"	TEXT,
	"Date"	DATETIME,
	"Subject"	TEXT,
	"Comment"	TEXT,
	"Status"	TEXT,
	"UpdatedBy"	INT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "DocFiles";
CREATE TABLE "DocFiles" (
	"ID"	INTEGER,
	"RefNo"	TEXT,
	"FileName"	TEXT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "DocTrack";
CREATE TABLE "DocTrack" (
	"ID"	INTEGER,
	"RefNo"	TEXT,
	"Status"	INTEGER,
	"Courier"	TEXT,
	"TrackingId"	TEXT,
	"UpdatedBy"	Text,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "Signatory";
CREATE TABLE "Signatory" (
	"ID"	INTEGER,
	"user_id"	INTEGER UNIQUE,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "Status";
CREATE TABLE "Status" (
	"ID"	INTEGER,
	"Status"	TEXT UNIQUE,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "StatusAudit";
CREATE TABLE "StatusAudit" (
	"id"	INTEGER,
	"RefNo"	VARCHAR(50),
	"OldStatus"	TEXT,
	"NewStatus"	TEXT,
	"ChangedBy"	INT,
	"ChangedAt"	TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY("id" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "UserRights";
CREATE TABLE "UserRights" (
	"ID"	INTEGER,
	"refno"	TEXT,
	"user_id"	TEXT,
	"rights"	TEXT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "Users";
CREATE TABLE "Users" (
	"ID"	INTEGER,
	"name"	TEXT,
	"email"	TEXT,
	"password"	TEXT,
	"app"	TEXT,
	"Type"	TEXT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "UsersBranch";
CREATE TABLE "UsersBranch" (
	"ID"	INTEGER,
	"user_id"	INTEGER,
	"branch"	TEXT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TABLE IF EXISTS "UsersDept";
CREATE TABLE "UsersDept" (
	"ID"	INTEGER,
	"user_id"	INTEGER,
	"dept"	TEXT,
	PRIMARY KEY("ID" AUTOINCREMENT)
);
DROP TRIGGER IF EXISTS "after_status_update";
CREATE TRIGGER after_status_update
AFTER UPDATE ON DocDetails
FOR EACH ROW
WHEN OLD.Status <> NEW.Status
BEGIN
    INSERT INTO StatusAudit (RefNo, OldStatus, NewStatus, ChangedBy, ChangedAt)
    VALUES (NEW.RefNo, OLD.Status, NEW.Status, NEW.UpdatedBy, CURRENT_TIMESTAMP);
END;
COMMIT;
