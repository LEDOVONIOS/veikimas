interface SSLInfoProps {
  certificate: {
    expiryDate: Date | null;
    daysUntilExpiry: number | null;
    issuer: string | null;
  };
}

export default function SSLInfo({ certificate }: SSLInfoProps) {
  const getExpiryStatus = (days: number | null) => {
    if (days === null) return { color: 'text-gray-600', bg: 'bg-gray-100', status: 'Unknown' };
    if (days < 0) return { color: 'text-red-600', bg: 'bg-red-100', status: 'Expired' };
    if (days <= 7) return { color: 'text-red-600', bg: 'bg-red-100', status: 'Critical' };
    if (days <= 30) return { color: 'text-yellow-600', bg: 'bg-yellow-100', status: 'Warning' };
    return { color: 'text-green-600', bg: 'bg-green-100', status: 'Valid' };
  };

  const formatExpiryDate = (date: Date | null) => {
    if (!date) return 'Unknown';
    return new Date(date).toLocaleDateString([], {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const status = getExpiryStatus(certificate.daysUntilExpiry);

  return (
    <div className="bg-white rounded-lg shadow-md p-6">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-xl font-semibold">SSL Certificate</h2>
        <span className={`px-3 py-1 text-sm font-medium rounded-full ${status.bg} ${status.color}`}>
          {status.status}
        </span>
      </div>

      <div className="space-y-4">
        <div className="flex items-start">
          <svg className="w-5 h-5 mr-3 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <div className="flex-1">
            <div className="text-sm text-gray-600">Expires on</div>
            <div className="font-medium">{formatExpiryDate(certificate.expiryDate)}</div>
          </div>
        </div>

        {certificate.daysUntilExpiry !== null && (
          <div className="flex items-start">
            <svg className="w-5 h-5 mr-3 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div className="flex-1">
              <div className="text-sm text-gray-600">Days until expiry</div>
              <div className={`font-medium ${status.color}`}>
                {certificate.daysUntilExpiry < 0 
                  ? `Expired ${Math.abs(certificate.daysUntilExpiry)} days ago`
                  : `${certificate.daysUntilExpiry} days`
                }
              </div>
            </div>
          </div>
        )}

        {certificate.issuer && (
          <div className="flex items-start">
            <svg className="w-5 h-5 mr-3 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <div className="flex-1">
              <div className="text-sm text-gray-600">Issued by</div>
              <div className="font-medium">{certificate.issuer}</div>
            </div>
          </div>
        )}

        {certificate.daysUntilExpiry !== null && certificate.daysUntilExpiry <= 30 && certificate.daysUntilExpiry > 0 && (
          <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
            <p className="text-sm text-yellow-800">
              <strong>⚠️ Certificate expiring soon!</strong> Renew your SSL certificate before it expires to avoid service disruption.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}