# encoding: UTF-8

module OpenBudget
  class Balance
    attr_reader :accounts

    def initialize
      @accounts = {}
    end

    def add(type, year, balance)
      @accounts[type] ||= {}
      @accounts[type][year] ||= 0
      @accounts[type][year] = (@accounts[type][year] + balance).round(2)
    end

    def +(b)
      self.load_hash b.accounts
      self
    end

    def ==(b)
      @accounts == b.try(:accounts)
    end

    def empty?
      @accounts.keys.empty?
    end

    def as_json(options = nil)
      @accounts
    end

    def load_hash(accounts)
      accounts.each do |type, years|
        years.each do |year, balance|
          self.add(type, year, balance)
        end
      end unless accounts.blank?
    end

  end
end
