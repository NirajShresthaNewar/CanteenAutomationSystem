class User {
  final int id;
  final String username;
  final String email;
  final String contactNumber;
  final String? token;
  final String? schoolName;
  final int? schoolId;
  final String? approvalStatus;
  final DateTime? expiresAt;
  final String? profilePic;

  User({
    required this.id,
    required this.username,
    required this.email,
    required this.contactNumber,
    this.token,
    this.schoolName,
    this.schoolId,
    this.approvalStatus,
    this.expiresAt,
    this.profilePic,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      username: json['username'],
      email: json['email'],
      contactNumber: json['contact_number'],
      token: json['token'],
      schoolName: json['school_name'],
      schoolId: json['school_id'] != null 
          ? (json['school_id'] is String 
              ? int.parse(json['school_id']) 
              : json['school_id'])
          : null,
      approvalStatus: json['approval_status'],
      expiresAt: json['expires_at'] != null
          ? DateTime.parse(json['expires_at'])
          : null,
      profilePic: json['profile_pic'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'username': username,
      'email': email,
      'contact_number': contactNumber,
      'token': token,
      'school_name': schoolName,
      'school_id': schoolId,
      'approval_status': approvalStatus,
      'expires_at': expiresAt?.toIso8601String(),
      'profile_pic': profilePic,
    };
  }
} 