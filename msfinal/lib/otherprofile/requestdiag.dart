import 'package:flutter/material.dart';

class RequestDialog extends StatefulWidget {
  final String receiverName;
  final Function(String) onSendRequest;
  final String? existingPhotoRequest;
  final String? existingChatRequest;
  final VoidCallback? onCancelRequest;
  final VoidCallback? onSeeAllRequests;

  const RequestDialog({
    Key? key,
    required this.receiverName,
    required this.onSendRequest,
    this.existingPhotoRequest,
    this.existingChatRequest,
    this.onCancelRequest,
    this.onSeeAllRequests,
  }) : super(key: key);

  @override
  _RequestDialogState createState() => _RequestDialogState();
}

class _RequestDialogState extends State<RequestDialog> {
  String _selectedRequestType = 'Photo';

  @override
  Widget build(BuildContext context) {
    // Check if there are any pending requests
    final hasPhotoRequest = widget.existingPhotoRequest == 'pending';
    final hasChatRequest = widget.existingChatRequest == 'pending';
    final hasPendingRequest = hasPhotoRequest || hasChatRequest;

    return AlertDialog(
      title: Text(
        hasPendingRequest ? 'Request Status' : 'Send Request',
        style: const TextStyle(
          color: Colors.red,
          fontWeight: FontWeight.bold,
        ),
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'To: ${widget.receiverName}',
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 16),

          if (hasPendingRequest) ...[
            // Show pending request status
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.orange.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: Colors.orange,
                  width: 1.5,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.hourglass_bottom, color: Colors.orange, size: 20),
                      const SizedBox(width: 8),
                      const Text(
                        'Pending Request',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: Colors.orange,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  if (hasPhotoRequest)
                    _buildRequestStatusRow('Photo Request', 'Waiting for response', Icons.photo_library_outlined),
                  if (hasPhotoRequest && hasChatRequest)
                    const SizedBox(height: 8),
                  if (hasChatRequest)
                    _buildRequestStatusRow('Chat Request', 'Waiting for response', Icons.chat_outlined),
                ],
              ),
            ),
          ] else ...[
            // Show request type selection
            const Text(
              'Select Request Type:',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: Colors.grey,
              ),
            ),
            const SizedBox(height: 12),
            _buildRequestTypeOption('Photo', Icons.photo_library_outlined, 'Request More Photos'),
            _buildRequestTypeOption('Chat', Icons.chat_outlined, 'Start a Conversation'),
            const SizedBox(height: 20),
          ],
        ],
      ),
      actions: [
        if (hasPendingRequest) ...[
          // Cancel and See All buttons for pending requests
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              widget.onSeeAllRequests?.call();
            },
            child: const Text(
              'See All Requests',
              style: TextStyle(color: Colors.blue),
            ),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              widget.onCancelRequest?.call();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            child: const Text('Cancel Request'),
          ),
        ] else ...[
          // Normal send request buttons
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text(
              'Cancel',
              style: TextStyle(color: Colors.grey),
            ),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              widget.onSendRequest(_selectedRequestType);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            child: const Text('Send Request'),
          ),
        ],
      ],
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
      ),
    );
  }

  Widget _buildRequestStatusRow(String title, String subtitle, IconData icon) {
    return Row(
      children: [
        Icon(icon, color: Colors.grey.shade600, size: 18),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey.shade600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildRequestTypeOption(String value, IconData icon, String description) {
    final isSelected = _selectedRequestType == value;

    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedRequestType = value;
        });
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? Colors.red.withOpacity(0.1) : Colors.grey.withOpacity(0.05),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? Colors.red : Colors.transparent,
            width: 2,
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: isSelected ? Colors.red : Colors.grey[300],
                shape: BoxShape.circle,
              ),
              child: Icon(
                icon,
                color: isSelected ? Colors.white : Colors.grey[700],
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    value,
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                      color: isSelected ? Colors.red : Colors.black,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    description,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Colors.grey,
                    ),
                  ),
                ],
              ),
            ),
            if (isSelected)
              const Icon(
                Icons.check_circle,
                color: Colors.red,
                size: 20,
              ),
          ],
        ),
      ),
    );
  }
}