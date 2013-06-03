# encoding: UTF-8

module OpenBudget
  class BalanceCollection
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
      collection = BalanceCollection.new
      [accounts, b.accounts].each do |accounts|
        accounts.each do |type, years|
          years.each do |year, balance|
            collection.add(type, year, balance)
          end
        end
      end
      collection
    end

    def ==(b)
      @accounts == b.accounts
    end

    def empty?
      @accounts.keys.empty?
    end

    def as_json(options = nil)
      @accounts
    end
  end
end
