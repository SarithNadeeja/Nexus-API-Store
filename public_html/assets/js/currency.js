window.NexusCurrency = {
  formatLkr(amount, decimals = 2) {
    const value = Number(amount);
    if (!Number.isFinite(value)) {
      return 'LKR 0.00';
    }

    return `LKR ${value.toLocaleString('en-LK', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    })}`;
  },
};
