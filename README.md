# Document Management System (DMS)

## Overview
The Document Management System (DMS) is a comprehensive solution designed to streamline the creation, organization, storage, and retrieval of documents. The system supports document creation, approval workflows, dispatch details (such as email or postal dispatch), and more. It provides an intuitive interface for users to manage their documents effectively while ensuring compliance with business requirements and security standards.

## Features
### Document Creation:
Allows users to create and manage documents efficiently with required metadata such as department, branch, subject, and date.

### Dispatch Details:
Supports two types of dispatch options: Email and Post, enabling users to send documents electronically or physically.

### User Rights Management:
Admins can assign different rights to users, ensuring proper access control and functionality per user role.

###Document History:
Tracks the complete history of a document, including its creation, signatory approval, and dispatch details.

### Approval Workflows:
Documents go through a predefined approval process with signatories, ensuring proper authorization at each step.

### Search and Filtering:
Users can search for documents based on multiple criteria, such as department, date, or status, to quickly locate necessary information.

### Security:
Ensures document integrity and access control with robust user authentication and authorization mechanisms.

## Installation
### Prerequisites
Web Server: Apache/Nginx

Database: SQLite

PHP: Version 7.4 or higher

Other: Composer for dependency management

### Steps to Install
Clone the repository:

```bash
git clone https://github.com/your-repository/dms.git
```

### Configure the Database:

Create a new database in SQLite.

Import the provided SQL schema into the database.

Update the configuration:

Modify the config.php or .env file to reflect your database connection details and other configurations.

### Install dependencies:

If using Composer, run:

```bash
composer install
```

### Start the Web Server:

For Apache, ensure mod_rewrite is enabled and your virtual host points to the public/ directory.

For Nginx, configure the server to serve the application correctly.

### Access the System:

Open your browser and go to http://localhost or your configured domain to start using the system.

Usage
Login: Users can log in using their credentials. Based on user rights, the system will grant access to specific features.

Create Document: A user can create a new document by selecting the appropriate dispatch details (Email/Post) and providing necessary information such as department, subject, and signatory.

Approve Documents: The system supports approval workflows. Users with signatory rights can approve or reject documents.

Dispatch Documents: After approval, users can send documents via Email or Post.

## Contributing
If you would like to contribute to this project, please follow the steps below:

Fork the repository.

Create a new branch (git checkout -b feature/your-feature).

Make changes and commit them (git commit -am 'Add new feature').

Push the branch to your fork (git push origin feature/your-feature).

Submit a pull request.
